# 06 — Form submission: server-side specification

**Status:** specification only. Nothing in this document is built yet.

**What *is* built:** `wp-content/plugins/piecyfer-core/src/Widgets/FormWidget.php`
and `assets/css/form.css` — the presentation half. They render every form on the
site byte-for-byte and own every control id, so no saved data is at risk.

**What is not built:** everything that happens after the visitor presses Send.
With only `FormWidget.php` in place and Elementor Pro deactivated, all nine forms
render perfectly and every submission fails with a bare `400`. **Do not
deactivate Pro until section 9 of this document is done.**

Every claim below is cited to `file:line` in
`wp-content/plugins/elementor-pro/` (referred to as *Pro*) or
`wp-content/plugins/elementor/` (*free*). Read the cited lines before changing
anything here.

---

## 0. What this site actually runs

| | |
|---|---|
| Live form widget instances | **9** (contact, consultation, two footers, five job-application forms) |
| Populated own settings across them | 73 |
| Field types in use | `tel` ×17, `email` ×9, `text` ×9, `textarea` ×7, `recaptcha_v3` ×7, `upload` ×5, `hidden` ×5, `acceptance` ×3, `select` ×2, `checkbox` ×1, `html` ×1 |
| `submit_actions` saved | **absent on all nine** — see §3 |
| Dynamic tags inside form settings | **none** — no form widget carries a `__dynamic__` map |
| reCAPTCHA v3 keys configured | yes, both site and secret (§5) |
| Files already on disk from uploads | 33 PDFs in `wp-content/uploads/elementor/forms/` |
| Submissions rows in the DB | 317 (§8) |

Re-derive with `C:\xampp\php\php.exe _project\scripts\dump-eldata.php` (strip the
leading PHP startup warning; JSON starts at the first `[`).

> A note on counts: a raw scan of *all* `_elementor_data` postmeta rows reports
> ~76 form widgets and much larger field counts. That figure includes revisions
> and autosaves. The **9** above is the live set and is the number that matters.

---

## 1. The AJAX submit endpoint and the nonce flow

### 1.1 Action names

```
Pro modules/forms/classes/ajax-handler.php:306-309
public function __construct() {
    add_action( 'wp_ajax_elementor_pro_forms_send_form',        [ $this, 'ajax_send_form' ] );
    add_action( 'wp_ajax_nopriv_elementor_pro_forms_send_form', [ $this, 'ajax_send_form' ] );
}
```

* Endpoint: `POST /wp-admin/admin-ajax.php`
* Action string: **`elementor_pro_forms_send_form`**
* Registered for logged-in **and** logged-out users.

This is *not* Elementor's ajax manager. The manager
(`elementor/ajax/register_actions`) is used only for the editor panel action
`pro_forms_panel_action_data` (Pro `modules/forms/module.php:177-179`), which
runs through `wp_ajax_elementor_ajax` and *is* nonce-protected.

The handler object is constructed lazily, gated on the POST action string:

```
Pro modules/forms/classes/ajax-handler.php:32-35
public static function is_form_submitted() {
    return wp_doing_ajax() && isset( $_POST['action'] ) && 'elementor_pro_forms_send_form' === $_POST['action'];
}
```
Called from Pro `modules/forms/module.php:304-318`, which then fires
`do_action( 'elementor_pro/forms/form_submitted', $this )`.

### 1.2 POST keys read

| Key | Read at | Notes |
|---|---|---|
| `action` | ajax-handler.php:34 | must equal `elementor_pro_forms_send_form` |
| `post_id` | ajax-handler.php:64, :270 | raw, unsanitised; identifies the document holding the form |
| `form_id` | ajax-handler.php:76 | the Elementor element id, e.g. `d998c99` |
| `queried_id` | ajax-handler.php:67-71 | optional; falls back to `post_id` |
| `form_fields[<custom_id>]` | ajax-handler.php:121 | the field values; names come from `Form_Base::get_attribute_name()`, form-base.php:261-263 |
| `referrer` | form-record.php:180 | **added by JS**, not a hidden input — double `r` |
| `referer_title` | form-record.php:187 | hidden input, single `r` — see §7 |
| `g-recaptcha-response` | recaptcha-handler.php:131 | injected by the recaptcha handler on submit |
| `$_FILES['form_fields']` | upload.php:163, :326, :498 | |

`$post_data = $_POST;` wholesale at ajax-handler.php:62. Slashes stripped once at
form-record.php:342 (`stripslashes_deep`).

### 1.3 Nonce: there isn't one

**Definitive: the public submit endpoint has no nonce, no capability check, no
referer check and no rate limit.**

* No `wp_verify_nonce` / `check_ajax_referer` anywhere in ajax-handler.php.
* The widget prints no nonce field — Pro `modules/forms/widgets/form.php:2355-2365`
  emits only `post_id`, `form_id`, `referer_title` and (when `is_singular()`)
  `queried_id`. `FormWidget::render_widget()` reproduces exactly that set.
* The JS does not add one:
  ```js
  // Pro assets/js/form.<hash>.bundle.js:217-222
  getFormData() {
    const formData = new FormData( this.elements.$form[0] );
    formData.append( 'action', this.getSettings( 'action' ) );   // elementor_pro_forms_send_form
    formData.append( 'referrer', location.toString() );
    return formData;
  }
  ```
* A nonce *does* exist in the localized config —
  `wp_create_nonce( 'elementor-pro-frontend' )`, Pro `plugin.php:258`, printed as
  `ElementorProFrontendConfig` at `plugin.php:282-286` — but the form handler
  reads only `elementorProFrontend.config.ajaxurl` (form bundle:174).
* Pro documents the choice: ajax-handler.php:33 —
  *"PHPCS - No nonce is required, all visitors may send the form."*

Free's editor nonce, for reference:
`elementor/core/common/modules/ajax/module.php:23` (`const NONCE_KEY = 'elementor_ajax'`),
created :222-224, verified :236-238, HTTP 401 `Token Expired.` on failure :130-133.
**It protects the editor path only.**

**Decision for the rebuild.** Adding a nonce is the single highest-value security
change available, and it is nearly free: mint `wp_create_nonce( 'piecyfer_form' )`
in the widget render, print it as a hidden `_pcf_nonce`, verify with
`wp_verify_nonce` at the top of the handler. The one real cost is page caching —
a cached page serves a stale nonce, which for a logged-out visitor is still valid
for 12–24 h (WP nonce tick) and then silently fails. Mitigate by re-minting the
nonce from a tiny uncached endpoint on first interaction, or accept the 24 h
window. **Ship the nonce; do not ship a bare copy of Pro's open endpoint.**

### 1.4 Re-locating the widget server side

```
Pro modules/forms/classes/ajax-handler.php:74-119
 74  Plugin::elementor()->db->switch_to_post( $queried_id );
 79  $document = $elementor->documents->get( $post_id );
 84  $form     = Module::find_element_recursive( $document->get_elements_data(), $form_id );
 87  if ( ! empty( $form['templateID'] ) ) { … global-widget indirection … }
 98  if ( empty( $form ) ) { INVALID_FORM; send(); }
105  $widget = $elementor->elements_manager->create_element_instance( $form );
106  $form['settings'] = $widget->get_settings_for_display();   // <- defaults + dynamic tags
107  $form['settings']['id']           = $form_id;
108  $form['settings']['form_post_id'] = $template_id ?: $post_id;
111  $form['settings']['edit_post_id'] = $post_id;
```

`find_element_recursive` is a plain depth-first id match over `$element['elements']`
— Pro `modules/forms/module.php:92-108`, returns `false` on miss.

Two things the rebuild must add, because Pro does neither:

1. **Assert the found element is a `form` widget.** Pro accepts any element id in
   any document `documents->get()` will hand over — published or not.
2. **Do not let a client-supplied `queried_id` steer `switch_to_post()`** without
   validating it. On this site nothing depends on it (no form uses dynamic tags),
   but it is a free foothold to close. See §7.

### 1.5 Response shape

`Ajax_Handler::send()` — ajax-handler.php:258-283. Both branches are HTTP 200.

Success:
```json
{ "success": true,
  "data": { "message": "<success message>", "data": { /* action-supplied keys */ } } }
```
The only core inner key is `redirect_url`, set by `actions/redirect.php:76` and
consumed by the JS at form bundle:145-149.

Error:
```json
{ "success": false,
  "data": { "message": "<HTML, <br>-joined>",
            "errors":  { "<field custom_id>": "<message>" },
            "data":    {} } }
```
* :266-268 — keyed field errors with no top-level message get the `INVALID_FORM`
  message added.
* :272 — `implode( '<br>', $this->messages['error'] )`.
* :273-276 — **admin-only leak channel:** if `current_user_can( 'edit_post', $post_id )`,
  `admin_error` messages are appended inside
  `<div class="elementor-forms-admin-errors">` plus *"This message is not visible
  to site visitors."* Keep this capability check exactly.

The JS injects `response.data.message` and each `errors` entry as **raw HTML**
(form bundle:229-256), placing field errors next to `#form-field-<custom_id>`.
Messages are therefore trusted, server-authored strings. **Never echo a
user-supplied value into them.**

---

## 2. Validation

### 2.1 Pipeline

```
Pro modules/forms/classes/ajax-handler.php:121-134
121  $record = new Form_Record( $post_data['form_fields'], $form );
123  if ( ! $record->validate( $this ) ) { … send(); }
130  $record->process_fields( $this );
132  if ( ! empty( $this->errors ) ) { $this->send(); }
```
Line 125 (`add_error( $record->get('errors') )`) is vestigial — `Form_Record` has
no `errors` property (form-record.php:12-17), so it passes `null`. Do not copy it.

### 2.2 Normalisation before validation

`Form_Record::set_fields()` — form-record.php:226-257 — builds one entry per
repeater row:

```php
[ 'id' => custom_id, 'type' => field_type, 'title' => field_label,
  'value' => '', 'raw_value' => '', 'required' => ! empty( $row['required'] ) ]
```
Upload rows additionally copy `file_sizes`, `file_types`, `max_files`,
`attachment_type` (:237). Array values are `implode(', ')`d (:249) before
sanitising (:253).

`Form_Record::sanitize_field()` — form-record.php:259-298:

| Type | Sanitiser |
|---|---|
| `text`, `password`, `hidden`, `search`, `checkbox`, `radio`, `select` | `sanitize_text_field` |
| `url` | `esc_url_raw` |
| `textarea` | `sanitize_textarea_field` |
| `email` | `sanitize_email` |
| anything else | `apply_filters( "elementor_pro/forms/sanitize/{$type}", … )` → the field class's `sanitize_field()`; base default `sanitize_text_field` (field-base.php:70-72), `Number` returns `intval()` (number.php:92-94) |

### 2.3 Required

```
Pro modules/forms/classes/form-record.php:44-82
47  if ( ! empty( $field['required'] ) && '' === $field['value'] && 'upload' !== $field_type )
48      $ajax_handler->add_error( $id, Ajax_Handler::get_default_message( FIELD_REQUIRED, $this->form_settings ) );
65  do_action( "elementor_pro/forms/validation/{$field_type}", $field, $this, $ajax_handler );
79  do_action( 'elementor_pro/forms/validation', $this, $ajax_handler );
81  return empty( $ajax_handler->errors );
```

Four behaviours the rebuild must decide on deliberately:

1. **Strict `'' ===`.** Because `Number::sanitize_field` returns `intval('') === 0`
   (an int), **a required number field submitted empty passes.** Same hole for any
   sanitiser returning a non-string. Fix in the rebuild.
2. **`upload` is excluded** and handled entirely inside `Upload::validation`
   (upload.php:343-352).
3. **Only keyed errors fail validation.** `add_error_message` (:226-231) sets
   `is_success = false` but leaves `$errors` empty, so `validate()` still returns
   `true` and the pipeline continues to `process_fields`, bailing only at :132.
4. **`Ajax_Handler::add_error` uses `+=` for arrays** (:233-243) — union, so an
   existing key is never overwritten: first error per field wins. The scalar path
   overwrites. Keep or fix, but know which.

### 2.4 Per-type validation

Registered field classes: Pro `modules/forms/registrars/form-fields-registrar.php:33-41`
— `Time, Date, Tel, Number, Acceptance, Upload, Step`. Hooked at
`field-base.php:84-95`.

| Type | Where | Rule |
|---|---|---|
| `tel` | fields/tel.php:30-37 | skip if empty; `/^[0-9()#&+*-=.]+$/` → *"The field accepts only numbers and phone characters (#, -, *, etc)."* — **17 fields on this site** |
| `time` | fields/time.php:82-90 | skip if empty; `/^(([0-1][0-9])|(2[0-3])):[0-5][0-9]$/` |
| `number` | fields/number.php:79-90 | `field_max` / `field_min` bounds |
| `date` | fields/date.php | **no server validation at all** — client `pattern` + `min`/`max` only |
| `acceptance` | fields/acceptance.php | **no server validation** — relies on the generic required check (unchecked ⇒ key absent ⇒ `''`) — **3 fields** |
| `email` | *no field class exists* | **no server-side email validation whatsoever**, only `sanitize_email()`. Browser `type="email"` is the only gate — **9 fields**. Add `is_email()` in the rebuild. |
| `url` | *no field class* | `esc_url_raw` only |
| `upload` | fields/upload.php:304-371 | §6 |
| `step` | fields/step.php | none |

Form-level validators on `elementor_pro/forms/validation`, in construction order
(Pro `modules/forms/module.php:270-277`):

1. `Recaptcha_Handler::validation` — recaptcha-handler.php:293, only if `is_enabled()`
2. `Recaptcha_V3_Handler::validation` — inherited, registered at recaptcha-v3-handler.php:149
3. `Honeypot_Handler::validation` — honeypot-handler.php:97, body :47-66. **Opt-in
   only**: it is a `honeypot` *field type* an editor must add. No form on this site
   uses it, so there is currently zero bot friction beyond reCAPTCHA.
4. `Akismet::validation` — classes/akismet.php:18, gated on `class_exists('\Akismet')`
   and a licence feature (module.php:275-277). **Not active here.**

### 2.5 Error keying and the message controls

Errors are keyed by the repeater's `custom_id`; the JS finds the DOM node by
`#form-field-<key>` (form bundle:233), matching `Form_Base::get_attribute_id()`
(form-base.php:265-267). Pseudo-keys used by honeypot (`invalid_form`) and akismet
(`akismet`) have no DOM node and silently produce no inline message.

Defaults — ajax-handler.php:37-46, reproduced verbatim in
`FormWidget::get_default_messages()`:

| Const | Value | English |
|---|---|---|
| `SUCCESS` | `success` | Your submission was successful. |
| `ERROR` | `error` | Your submission failed because of an error. |
| `FIELD_REQUIRED` | `required_field` | This field is required. |
| `INVALID_FORM` | `invalid_form` | Your submission failed because the form is invalid. |
| `SERVER_ERROR` | `server_error` | Your submission failed because of a server error. |
| `SUBSCRIBER_ALREADY_EXISTS` | `subscriber_already_exists` | Subscriber already exists. |

Override lookup — ajax-handler.php:48-59 — is `<const value> . '_message'`, gated
on the `custom_messages` switcher:

```php
if ( ! empty( $settings['custom_messages'] ) && isset( $settings[ $id . '_message' ] ) )
    return $settings[ $id . '_message' ];
```

> **Upstream bug, reproduce the controls but not the bug.** form.php registers
> `success_message` (:994), `error_message` (:1012), `server_message` (:1030),
> `invalid_message` (:1048), `required_field_message` (:1066). The lookup asks for
> `server_error_message` and `invalid_form_message`, which never exist — so
> **`server_message` and `invalid_message` are dead controls** and always fall back
> to the hard-coded English strings. `FormWidget` keeps all five control ids
> (mandatory, they are saved on all nine forms); the new handler should map them
> correctly: `server_message` → SERVER_ERROR, `invalid_message` → INVALID_FORM.

**Live consequence on this site:** `custom_messages` is *not* saved on any of the
nine forms, so it is `''` and every custom message is ignored. Worse, controls
hidden by a failing condition come back as `NULL` from `get_settings_for_display()`
(free `includes/base/controls-stack.php:1230-1233`) — verified: `success_message => NULL`.
The strings visitors see today are Pro's English defaults, **not** the
`"The form was sent successfully."` values stored in the widget (which are
inherited from the VamTam demo import). Changing this is a visible behaviour
change; do it knowingly, not by accident.

`required_field_message` additionally requires `form_validation => 'custom'`
(form.php:1075). `form_validation` also drives `novalidate` on the `<form>`
(form.php:2340-2342); it is `''` on all nine forms, so browser validation is live
and the custom message never applies.

---

## 3. The default submit action — definitive

### 3.1 The literal default

```
Pro modules/forms/widgets/form.php:828
$default_submit_actions = [ 'email' ];

Pro modules/forms/widgets/form.php:840
$default_submit_actions = apply_filters( 'elementor_pro/forms/default_submit_actions', $default_submit_actions );

Pro modules/forms/widgets/form.php:842-854
$this->add_control( 'submit_actions', [ …, 'default' => $default_submit_actions, … ] );
```

Pro's own Submissions component appends to it:

```
Pro modules/forms/submissions/component.php:173-175
add_filter( 'elementor_pro/forms/default_submit_actions', function ( $actions ) {
    return array_merge( $actions, [ 'save-to-database' ] );
} );
```

**Effective control default on this install: `[ 'email', 'save-to-database' ]`.**

### 3.2 Does the default apply when the saved data has no `submit_actions`?

**Yes.** The chain, end to end:

1. ajax-handler.php:105-106 replaces the saved settings wholesale with
   `$widget->get_settings_for_display()`.
2. `get_settings_for_display()` → `get_active_settings()` → `get_settings()` →
   `get_init_settings()` (free `includes/base/controls-stack.php:1261-1266`, :2217-2235).
3. `get_init_settings()` calls `$control_obj->get_value( $control, $settings )` per
   control (:2231).
4. ```
   free includes/controls/base-data.php:61-63
   if ( isset( $settings[ $control['name'] ] ) ) $value = $settings[ $control['name'] ];
   else                                          $value = $control['default'];
   ```

**The test is `isset()`, not "empty".** Therefore:

* **Key absent** → the default applies → the actions run.
* **Key present but `[]`** (an editor cleared every action) → `isset()` is true →
  the empty array wins → ajax-handler.php:151-186 iterates zero times → the
  submission **silently reports success and does nothing**.

Verified empirically: `submit_actions` is absent from every one of the nine live
forms (Elementor strips settings equal to their default before saving —
free `includes/base/element-base.php:612-634`, `core/base/document.php:1344-1351`),
and a runtime dump of post 1273 / widget `46162aa` resolves
`submit_actions => ['email','save-to-database']`.

> **So: every submission on this site today sends the Email action and writes a
> submissions row. `email2` is registered and its `*_2` settings are saved
> (leftover VamTam demo values pointing at `office@vamtam.com`), but `email2` is
> not in `submit_actions`, so Email 2 never fires. Do not "fix" those values —
> just leave the controls in place.**

`FormWidget` registers the same literal `[ 'email' ]` default and the same filter,
but does *not* register a Submissions component. Until §9 ships a replacement,
the effective PieCyfer default is `[ 'email' ]` alone.

### 3.3 Dispatch loop

```
Pro modules/forms/classes/ajax-handler.php:136-186
138  $actions = $module->actions_registrar->get();
149  $record  = apply_filters( 'elementor_pro/forms/record/actions_before', $record, $this );
151  foreach ( $actions as $action ) {
152      if ( ! in_array( $action->get_name(), $form['settings']['submit_actions'], true ) ) continue;
158      try { $action->run( $record, $this ); $this->handle_bc_errors( $errors ); }
162      catch ( \Exception $e ) { admin error + user ERROR message }
185      do_action( 'elementor_pro/forms/actions/after_run', $action, $exception );
209  do_action( 'elementor_pro/forms/new_record', $record, $this );
211  $this->send();
```
Order is **registrar order, not `submit_actions` order**. `save-to-database` is
registered at priority 0 (component.php:170-172), so it runs *before* `email` —
a submission is recorded even if the mail then fails. Keep that ordering.

`handle_bc_errors` (:297-304) converts any newly-added error message into a thrown
exception, so legacy actions that only call `add_error_message` are treated as
failures.

Live registry order on this install:
`save-to-database, email, email2, redirect, webhook, mailchimp, drip,
activecampaign, getresponse, convertkit, mailerlite, slack, discord, popup`.

### 3.4 The Email action, in full

File: Pro `modules/forms/actions/email.php` (493 lines). `get_name()` → `'email'`
(:19-21). Section id `section_email`, condition `submit_actions => 'email'` (:33-36).

**Controls and their defaults** (all reproduced in `FormWidget::submit_actions()`):

| Control | Line | Default |
|---|---|---|
| `email_to` | 39-56 | `get_option('admin_email')` |
| `email_subject` | 61-77 | `sprintf( 'New message from "%s"', get_option('blogname') )` |
| `email_content` | 79-99 | **`[all-fields]`** |
| `email_from` | 103-117 | `'email@' . Utils::get_site_domain()` (core/utils.php:75-77 — `home_url()` host minus `www.`) |
| `email_from_name` | 119-133 | `get_bloginfo('name')` |
| `email_reply_to` | 135-145 | SELECT; the value is a **field `custom_id`**, options filled client-side |
| `email_to_cc` | 147-162 | `''` |
| `email_to_bcc` | 164-179 | `''` |
| `form_metadata` | 181-207 | `['date','time','page_url','user_agent','remote_ip','credit']` |
| `email_content_type` | 209-221 | `'html'` |

**Runtime (`run()`, :277-404).**

```
279  $send_html  = 'plain' !== $settings['email_content_type'];
280  $line_break = $send_html ? '<br>' : "\n";
282  $fields = [
283      'email_to'        => get_option( 'admin_email' ),
285      'email_subject'   => sprintf( 'New message from "%s"', get_bloginfo( 'name' ) ),
286      'email_content'   => '[all-fields]',
287      'email_from_name' => get_bloginfo( 'name' ),
288      'email_from'      => get_bloginfo( 'admin_email' ),
289      'email_reply_to'  => 'noreply@' . Utils::get_site_domain(),
290      'email_to_cc'     => '', 'email_to_bcc' => '',
291  ];
294  foreach ( $fields as $key => $default ) {
295      $setting = trim( $settings[ $key ] );
296      $setting = $record->replace_setting_shortcodes( $setting );
297      if ( ! empty( $setting ) ) $fields[ $key ] = $setting;
298  }
```

Note the **runtime fallbacks differ from the control defaults**: `email_from`
falls back to `get_bloginfo('admin_email')`, not `email@<domain>`; `email_subject`
uses `bloginfo('name')` where the control used `option('blogname')`. Harmless here
(all nine forms set both explicitly) but reproduce it rather than "tidy" it.

`replace_setting_shortcodes()` — form-record.php:300-314 — regex
`/(\[field[^]]*id="(\w+)"[^]]*\])/`, substituting `$this->fields[$id]['value']`,
optionally url-encoded. **This is what makes the Contact Us form's hand-built
`email_content` work**, e.g.
`Full Name: [field id="contact_us_full_name"]<br>Phone Number: …`.

**Reply-To** — `get_reply_to()`, :422-436: treats `email_reply_to` as an index into
the record's fields; only if `$sent_data[$index]` is non-empty **and** passes
`is_email()` does it become the reply-to. Otherwise `''`, producing a literal
`Reply-To: \r\n` header. Email 2 overrides this (email2.php) to use the raw text.

**Message construction** — `replace_content_shortcodes()`, :444-469:

```
445  $email_content = do_shortcode( $email_content );     // WP shortcodes first
448  if ( strpos( $email_content, '[all-fields]' ) !== false ) {
450      foreach ( $record->get( 'fields' ) as $field ) {
452          if ( MODE_ATTACH === ( $field['attachment_type'] ?? null ) ) continue;
456          $formatted = $this->field_formatted( $field );        // ":406-415"
457          if ( 'textarea' === $field['type'] && '<br>' === $line_break )
458              $formatted = str_replace( [ "\r\n", "\n", "\r" ], '<br />', $formatted );
461          $text .= $formatted . $line_break;
464      $email_content = str_replace( '[all-fields]', $text, $email_content );
```

`field_formatted()` → `"{title}: {value}"`, or bare `"{value}"` when the field has
no label, or `''` when both are empty.

**Line breaks:** HTML mode joins rows with `<br>` *and* converts newlines inside
textarea values to `<br />`; plain mode joins with `\n` and leaves textareas alone.

**Meta block** — :306-318:
```
$fields['email_content'] .= $line_break . '---' . $line_break . $line_break . $email_meta;
```
i.e. body, blank line, `---`, blank line, meta lines. Values from
`Form_Record::get_form_meta()`, form-record.php:158-214:

| Key | Label | Source |
|---|---|---|
| `date` | Date | `date_i18n( get_option('date_format') )` :166 |
| `time` | Time | `date_i18n( get_option('time_format') )` :173 |
| `page_url` | Page URL | `esc_url_raw( $_POST['referrer'] )` :180 — **client-supplied** |
| `page_title` | Page Title | `sanitize_text_field( $_POST['referer_title'] )` :187 — **client-supplied** |
| `user_agent` | User Agent | `$_SERVER['HTTP_USER_AGENT']` :194 |
| `remote_ip` | Remote IP | `Utils::get_client_ip()` :201 |
| `credit` | Powered by | literal `'Elementor'` :207 |

`Utils::get_client_ip()` (Pro core/utils.php:53-73) walks `HTTP_CLIENT_IP`,
`HTTP_X_FORWARDED_FOR`, `HTTP_X_FORWARDED`, `HTTP_X_CLUSTER_CLIENT_IP`,
`HTTP_FORWARDED_FOR`, `HTTP_FORWARDED`, `REMOTE_ADDR`, returning the first that
passes `FILTER_VALIDATE_IP`, else `127.0.0.1`. **Client-spoofable** — the headers
are checked before `REMOTE_ADDR`. Unless the site sits behind a proxy you control,
the rebuild should trust `REMOTE_ADDR` only.

**Headers** — :320-342:
```php
$headers  = sprintf( 'From: %s <%s>' . "\r\n", $fields['email_from_name'], $fields['email_from'] );
$headers .= sprintf( 'Reply-To: %s' . "\r\n", $email_reply_to );
if ( $send_html ) $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
$cc_header = $fields['email_to_cc'] ? 'Cc: ' . $fields['email_to_cc'] . "\r\n" : '';
$headers   = apply_filters( 'elementor_pro/forms/wp_mail_headers', $headers );   // BEFORE $cc_header is appended
```

**The send** — :359-365:
```php
$email_sent = wp_mail(
    $fields['email_to'],
    $fields['email_subject'],
    $fields['email_content'],
    $headers . $cc_header,
    array_merge( $attachments_mode_attach, $attachments_mode_both )
);
```

**Bcc is not a header** — :367-378: one extra `wp_mail()` per comma-separated
address, same subject/body/attachments, **without** the Cc header.

**Cleanup** — :380-382: `@unlink()` for `MODE_ATTACH` files only.

**Failure** — :397-403: `wp_mail()` returning false adds the SERVER_ERROR message
*and* throws, which the dispatch loop turns into an admin-only error line.
`do_action( 'elementor_pro/forms/mail_sent', … )` fires at :395 **before** the
success check — i.e. even on failure. Do not copy that ordering.

**Live values on this site (Contact Us, post 83):**
`email_to = info@piecyfer.com`, `email_from = info@piecyfer.com`,
`email_from_name = PieCyfer Contact`, `email_subject = New message from "Contact Us"`,
`email_content_type = html` (default), `form_metadata = [date, time, page_url, remote_ip]`.

### 3.5 Email 2

Pro `modules/forms/actions/email2.php` (52 lines). `class Email2 extends Email`.
The whole mechanism is one method:
```php
protected function get_control_id( $control_id ) { return $control_id . '_2'; }
```
So the section id is **`section_email_2`** (not `section_email2`) and the controls
are `email_to_2`, `email_subject_2`, `email_content_2`, `email_from_2`,
`email_from_name_2`, `email_reply_to_2`, `email_to_cc_2`, `email_to_bcc_2`,
`form_metadata_2`, `email_content_type_2`. Two overrides in
`register_settings_section()`: `email_reply_to_2` is retyped to TEXT defaulting to
`admin_email`, and `form_metadata_2` defaults to `[]`.

All ten `*_2` ids are saved on all nine forms and are reproduced by `FormWidget`.

---

## 4. What Elementor's own record object does

`Form_Record` (Pro `modules/forms/classes/form-record.php`) is worth reproducing
close to 1:1 — it is the object every action receives.

| Member | Line | Purpose |
|---|---|---|
| `sent_data, fields, form_type, form_settings, files, meta` | 12-17 | the whole state |
| `validate()` | 44-82 | §2.3 |
| `process_fields()` | — | fires `elementor_pro/forms/process/{type}` per field, then `elementor_pro/forms/process` |
| `get_field( [ 'type' => … ] )` | — | used by the reCAPTCHA and honeypot validators |
| `remove_field( $id )` | — | drops a field so it never reaches the email |
| `update_field( $id, $key, $value )` | — | how uploads rewrite `value` to a URL |
| `add_file( $id, $index, [path,url] )` | 316-327 | `url` becomes the literal `'attached'` for `MODE_ATTACH` |
| `replace_setting_shortcodes()` | 300-314 | `[field id="…"]` |
| `get_form_meta()` | 158-214 | §3.4 |

---

## 5. reCAPTCHA v3

**7 of the 9 forms carry a `recaptcha_v3` field.**

### 5.1 Where the keys live

```
Pro modules/forms/classes/recaptcha-v3-handler.php:16-21
const OPTION_NAME_V3_SITE_KEY         = 'elementor_pro_recaptcha_v3_site_key';
const OPTION_NAME_V3_SECRET_KEY       = 'elementor_pro_recaptcha_v3_secret_key';
const OPTION_NAME_RECAPTCHA_THRESHOLD = 'elementor_pro_recaptcha_v3_threshold';
const V3_DEFAULT_THRESHOLD            = 0.5;
const V3_DEFAULT_ACTION               = 'Form';

Pro modules/forms/classes/recaptcha-handler.php:18-24   (v2)
const OPTION_NAME_SITE_KEY   = 'elementor_pro_recaptcha_site_key';
const OPTION_NAME_SECRET_KEY = 'elementor_pro_recaptcha_secret_key';
```

They are plain `wp_options` rows. **Verified state on this install:**

| Option | State |
|---|---|
| `elementor_pro_recaptcha_v3_site_key` | SET (40 chars, prefix `6LcmQX`) |
| `elementor_pro_recaptcha_v3_secret_key` | SET (40 chars, prefix `6LcmQX`) |
| `elementor_pro_recaptcha_site_key` | SET (40 chars, prefix `6LcmQX`) |
| `elementor_pro_recaptcha_secret_key` | SET (40 chars, prefix `6LcmQX`) |
| `elementor_pro_recaptcha_v3_threshold` | SET, `0.5` |
| `elementor_pro_recaptcha_threshold` | not set (and unused by the code) |

> The v2 and v3 keys share the `6LcmQX` prefix. That is suspicious — a v2 secret
> will not validate a v3 token. No v2 field exists on the site so nothing is
> broken today, but **verify against the Google console before the cut-over**;
> if the v3 pair is actually a v2 pair, every reCAPTCHA-protected submission will
> start failing the moment we own the verification code and stop being masked by
> whatever currently happens.

**Migration:** none needed. `FormWidget::render_recaptcha_field()` reads Pro's
option names directly, and the new handler should too. Keeping the option names
means the Elementor → Settings → Integrations admin UI (which disappears with Pro)
is not on the critical path; it can be rebuilt later, or the values edited via
WP-CLI.

The admin fields, for whenever that UI is rebuilt: v2 at recaptcha-handler.php:61-74
(`pro_recaptcha_site_key`, `pro_recaptcha_secret_key`), v3 at
recaptcha-v3-handler.php:66-93 (`pro_recaptcha_v3_site_key`,
`pro_recaptcha_v3_secret_key`, `pro_recaptcha_v3_threshold` — number, min 0, max 1,
step 0.1, std 0.5). Elementor's settings API prefixes stored ids with `elementor_`.

### 5.2 Verification

```
Pro modules/forms/classes/recaptcha-handler.php:119-193
120  $fields = $record->get_field( [ 'type' => 'recaptcha_v3' ] );
124  if ( empty( $fields ) ) return;              // no captcha field -> no check at all
131  $recaptcha_response = $_POST['g-recaptcha-response'];
133  if ( empty( $recaptcha_response ) ) { add_error( 'The Captcha field cannot be blank…' ); return; }
149  $request  = [ 'body' => [ 'secret' => $secret, 'response' => $token, 'remoteip' => Utils::get_client_ip() ] ];
157  $response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', $request );
161  if ( 200 !== (int) $response_code ) { add_error( 'Can not connect to the reCAPTCHA server (%d).' ); return; }
172  if ( ! $this->validate_result( $result, $field ) ) { … }
191  $record->remove_field( $field['id'] );        // always, even on failure
```

Score and action check:
```
Pro modules/forms/classes/recaptcha-v3-handler.php:121-125
$action    = 'Form';
$action_ok = ! isset( $result['action'] ) ? true : $action === $result['action'];
return $action_ok && ( $result['score'] > self::get_recaptcha_threshold() );
```
* **Strict `>`** — a score exactly equal to the threshold fails.
* Threshold from the global option, clamped to 0…1 with 0.5 as fallback
  (recaptcha-v3-handler.php:39-45). **It is not a widget control** — form.php has
  no threshold control at all; its only reCAPTCHA controls are `recaptcha_size`
  (:337), `recaptcha_style` (:357) and `recaptcha_badge` (:378), all reproduced.
* The action string `Form` is a hard-coded const (:21), rendered as `data-action`
  (:105) and compared server-side (:122). Keep both in sync or every verification
  fails.

**On failure:** base message *"Invalid form, reCAPTCHA validation failed."*
(recaptcha-handler.php:173), refined from `$result['error-codes']` against the map
at :139-144 (`missing-input-secret`, `invalid-input-secret`,
`missing-input-response`, `invalid-input-response`). V3 additionally adds a
form-level message *"reCAPTCHA V3 validation failed, suspected as abusive usage"*
(recaptcha-v3-handler.php:116-119). The error is keyed to the captcha field id,
which has no visible input, so the inline message never renders — only the
form-level one is seen.

**Position in the pipeline:** `add_action( 'elementor_pro/forms/validation', …, 10, 2 )`
at recaptcha-handler.php:293, registered **only if `is_enabled()`**. That hook
fires at form-record.php:79 — after the per-field required/type loop, before
`process_fields`.

**Operational risk to carry over knowingly:** `wp_remote_post` with the default
5 s timeout and no retry. A Google outage returns
*"Can not connect to the reCAPTCHA server (0)"* and **blocks every submission on
seven of nine forms.** The rebuild should decide fail-open vs fail-closed
deliberately and log it; Pro fails closed with no alerting.

### 5.3 The token, client side

* Script: `elementor-recaptcha_v3-api` → `https://www.google.com/recaptcha/api.js?render=explicit`
  (recaptcha-handler.php:97-105). Registered in the constructor (:285), enqueued
  lazily inside `render_field()` (:222-223), skipped in preview (:108-110).
  Note v3 uses the **same `?render=explicit` URL as v2** — the widget is created
  explicitly, not via `render=<sitekey>`.
  `FormWidget::render_recaptcha_field()` reproduces this enqueue.
* Markup: `<div class="elementor-field" id="form-field-<custom_id>">` wrapping
  `<div class="elementor-g-recaptcha" data-sitekey data-type data-action="Form"
  data-badge data-size="invisible">` (recaptcha-handler.php:217-252 +
  recaptcha-v3-handler.php:102-109). The badge class `recaptcha_v3-<badge>` is
  added to the field group by a filter (recaptcha-v3-handler.php:140-146) — our
  widget adds it inline instead.
* Token flow (Pro form bundle:863-941): `grecaptcha.render( el, el.data() )` —
  the whole data-attribute set is the config. For v3 the handler **intercepts the
  submit button click**, calls `grecaptcha.execute( widgetId, { action } )`,
  writes `<input type="hidden" name="g-recaptcha-response">` into the form, then
  re-triggers submit.

> **The captcha gate is a click interceptor, nothing more.** Anything posting
> straight to `admin-ajax.php` supplies its own token or none. And if a form's
> saved config contains no `recaptcha_v3` field, `validation()` returns at :124
> with **no check whatsoever** — the two unprotected forms on this site are wide
> open. This is another argument for the nonce in §1.3 plus a rate limit.

---

## 6. File upload

Pro `modules/forms/fields/upload.php` (557 lines). **5 upload fields on this site;
33 PDFs already on disk.**

### 6.1 Controls

| Control | Line | Default |
|---|---|---|
| `attachment_type` | 44-61 | `link` (consts `MODE_LINK/ATTACH/BOTH` at :19-21) |
| `file_sizes` | 62-74 | none; options `1MB … floor(wp_max_upload_size()/1MB)MB` (:477-487) |
| `file_types` | 75-89 | none (comma-separated) |
| `allow_multiple_upload` | 90-100 | off |
| `max_files` | 101-112 | none |

All five are reproduced by `FormWidget::injected_field_controls()`;
`attachment_type`, `file_sizes` and `file_types` carry live data on this site.

### 6.2 Allowed types

```
Pro modules/forms/fields/upload.php:211-225
213  if ( empty( $field['file_types'] ) )
214      $field['file_types'] = 'jpg,jpeg,png,gif,pdf,doc,docx,ppt,pptx,odt,avi,ogg,m4a,mov,mp3,mp4,mpg,wav,wmv';
217  $file_extension  = pathinfo( $file['name'], PATHINFO_EXTENSION );
224  return in_array( $file_extension, $file_types_meta ) && ! in_array( $file_extension, $this->get_blacklist_file_ext() );
```

Deny-list — :232-295, filterable via `elementor_pro/forms/filetypes/blacklist`:
`php, php2, php3, php4, php5, php6, php7, phps, phtm, phtml, pht, phar, phpt,
hphp, shtml, swf, html, htm, hta, asp, aspx, cmd, csh, bat, jar, exe, com, js,
lnk, htaccess, htpasswd, ps1, ps2, py, rb, tmp, cgi, svg, svgz`.

**Extension only. No MIME sniffing anywhere** — no `wp_check_filetype_and_ext`,
no `finfo`, no `getimagesize`. `$file['type']` (client-supplied Content-Type) is
read into the fixed indices at :161 and never validated. WP's own
`wp_handle_upload` / `upload_mimes` machinery is bypassed entirely.

### 6.3 Size

```
Pro modules/forms/fields/upload.php:194-201
$allowed_size   = ! empty( $field['file_sizes'] ) ? $field['file_sizes'] : wp_max_upload_size() / 1024**2;
return ( $file['size'] < $allowed_size * 1024**2 );   // strict <
```
The field control can only **lower** the PHP/WP ceiling, never raise it. PHP's own
limits fire first as `UPLOAD_ERR_INI_SIZE` (:311). The client-side pre-check reads
`data-maxsize` / `data-maxsize-message` (form bundle:191-209), written by
`render()` :133-141 — `FormWidget::render_upload_field()` reproduces both.

### 6.4 Multiple files, and two bugs

`render()` :128-131 sets `multiple="multiple"` and `name="form_fields[<id>][]"`.
`fix_file_indices()` :151-184 rewrites PHP's flat `$_FILES` shape into
`$_FILES['form_fields'][id][n]['name']`. `max_files` enforced at :328-339.

Two defects to fix rather than copy:

1. **:326** `$files = Utils::_unstable_get_super_global_value( $_FILES, 'form_fields' );`
   returns `null` when no file part is posted; :329 / :341 then index and
   `foreach` over `null`. Null-guard it.
2. **:344 / :351 / :358** use `return`, not `continue`, inside the per-file loop —
   so **only the first file is inspected** once any of those branches hits.

### 6.5 Storage

* Directory: `wp_upload_dir()['basedir'] . '/elementor/forms'` (:378-397),
  filter `elementor_pro/forms/upload_path`.
  Here: `wp-content/uploads/elementor/forms/`, served at
  `/wp-content/uploads/elementor/forms/`.
* URL: `:406-426`, filter `elementor_pro/forms/upload_url`.
* Guard files — `get_ensure_upload_dir()` :433-470 writes `index.php`
  (*"Silence is golden."*) and an `.htaccess` containing:
  ```
  Options -Indexes
  <ifModule mod_headers.c>
  	<Files *.*>
         Header set Content-Disposition attachment
  	</Files>
  </IfModule>
  ```
  **Actual state on disk right now: `.htaccess` present, `index.php` MISSING.**
  So the early-out probe at :435 never fires (harmless), and whatever removed
  `index.php` could equally remove `.htaccess`.
* Naming — `process_field()` :496-531:
  ```php
  $filename = uniqid() . '.' . pathinfo( $file['name'], PATHINFO_EXTENSION );
  $filename = wp_unique_filename( $uploads_dir, $filename );
  … Plugin::instance()->php_api->move_uploaded_file( $file['tmp_name'], $new_file );
  @chmod( $new_file, 0644 );
  $record->add_file( $id, $index, [ 'path' => $new_file, 'url' => $this->get_file_url( $filename ) ] );
  ```
  The original filename is discarded. Confirmed on disk: `66fd364d7faff.pdf`, etc.
* `set_file_fields_values()` :541-550 (hooked on `elementor_pro/forms/process`,
  :554) sets the record field's `value` to the comma-joined URLs and `raw_value`
  to the comma-joined paths — which is what lands in the email body and the
  submissions table.

### 6.6 Attach vs link

| Mode | Email | Disk |
|---|---|---|
| `link` (default) | public URL in the body | stays **forever** |
| `attach` | `wp_mail` attachment; excluded from `[all-fields]` (email.php:452) | **deleted after send** (email.php:380-382) |
| `both` | attached *and* linked | stays forever |

### 6.7 Security — what we inherit, stated plainly

1. **Unauthenticated write-to-disk.** `wp_ajax_nopriv_*` + no nonce + no rate
   limit + no mandatory captcha. `post_id` and `form_id` are public in the page
   HTML. Anyone on the internet can drop files into
   `wp-content/uploads/elementor/forms/`. This is a **free anonymous file host and
   a disk-exhaustion vector**.
2. **Protection is `.htaccess`-only**, and half of it is inside
   `<ifModule mod_headers.c>` so it silently no-ops without `mod_headers`. On
   nginx, on LiteSpeed without `.htaccess` translation, or on Apache with
   `AllowOverride None`, you get directory listing and inline rendering.
   `index.php` is already gone.
3. **Extension-only deny-list.** `pathinfo( …, PATHINFO_EXTENSION )` reads the
   last segment only — Apache `mod_mime` multi-extension parsing (`x.php.jpg`) is
   the classic bypass on a misconfigured server. The list also omits `pl`, `sh`,
   `jsp`, `war`, `class`, `inc`, `xhtml`, `mhtml`. The allow-list is the real
   gate, and it is whatever an editor typed into `file_types`.
4. **No content verification at all.** A `.pdf`-named file can contain anything.
   With `Content-Disposition: attachment` this is stored-malware distribution
   rather than direct RCE — but only where that header actually applies (see 2).
5. **Enumerable filenames.** `uniqid()` is a hex encoding of the current time to
   the microsecond — sequential and predictable, not random. Anyone who knows
   roughly when a submission happened can brute-force a small keyspace and fetch
   another person's file with no authentication. **For a site collecting CVs
   through five job-application forms this is a live confidentiality problem, not
   a theoretical one.** 33 PDFs are sitting there now.
6. **No retention or cleanup.** Only `MODE_ATTACH` files are unlinked. `link` (the
   default) and `both` accumulate forever. No cron sweep, no TTL, no directory
   size cap. The submissions trash cron (submissions/component.php:142-152)
   deletes DB rows only.

**Minimum bar for the rebuild:** nonce + rate limit; CSPRNG filenames
(`wp_generate_password( 32, false )` or `bin2hex( random_bytes( 16 ) )`); MIME
verified against extension with `wp_check_filetype_and_ext()`; allow-list only;
storage outside the web root behind a signed-URL download endpoint (or, at
minimum, a server-config-level deny for the directory rather than `.htaccess`);
and a retention sweep. Treat the 33 existing files as already-exposed and rotate
the directory when the new storage lands.

---

## 7. `queried_id` and `referer_title`

An exhaustive grep across both plugins (excluding `.min.js`) finds only these.

### `queried_id`

| File:line | Role |
|---|---|
| Pro modules/forms/widgets/form.php:2361-2365 | **Producer.** `<?php if ( is_singular() ) { ?><input type="hidden" name="queried_id" value="<?php echo get_the_ID(); ?>"/>`. Pro's own comment at :2362: *"`queried_id` may be different from `post_id` on Single theme builder templates."* |
| Pro modules/forms/classes/ajax-handler.php:66-74 | **Consumer.** `$queried_id = $_POST['queried_id'] ?? $post_id;` then `Plugin::elementor()->db->switch_to_post( $queried_id );` |

**Nothing in the free plugin touches it. No JS. No dynamic tag.** It is pure
server-side PHP emitted at render time. (Pro echoes it unescaped, unlike the other
three inputs — harmless for an int, but `FormWidget` uses `esc_attr()`.)

### `referer_title`

| File:line | Role |
|---|---|
| Pro modules/forms/widgets/form.php:2348-2352 | **Producer.** `$referer_title = trim( wp_title( '', false ) ); if ( ! $referer_title && is_home() ) $referer_title = get_option( 'blogname' );` |
| Pro modules/forms/widgets/form.php:2359 | the hidden input |
| Pro modules/forms/classes/form-record.php:187 | **Consumer.** `page_title` meta ← `sanitize_text_field( wp_unslash( $_POST['referer_title'] ) )` |
| Pro modules/forms/submissions/actions/save-to-database.php:103 | → the `referer_title` DB column |
| Pro modules/forms/submissions/database/migrations/referer-extra.php:11,17 | `ADD COLUMN referer_title varchar(300) null AFTER referer;` + index |
| Pro modules/forms/submissions/database/query.php:291, :301, :839 | search / distinct-referer list / row hydration |

Again: **no JS, no dynamic tag** — server-rendered PHP only.

### The third, JS-supplied sibling

`referrer` (double `r`) is **not** a hidden input. Pro form bundle:220 —
`formData.append( 'referrer', location.toString() )`. Consumed at
form-record.php:180 for the `page_url` meta and at save-to-database.php:99-102
(with `preview_id`/`preview_nonce`/`preview` stripped) for the `referer` column.
Do not confuse the two spellings.

### The cache bug, precisely

All of `post_id`, `queried_id` and `referer_title` are baked into the HTML **at
render time** from the *current* request:

* `post_id` ← `Utils::get_current_post_id()` (Pro core/utils.php:79-85) →
  `documents->get_current()->get_main_id()`
* `queried_id` ← `get_the_ID()`, inside an `is_singular()` guard
* `referer_title` ← `wp_title( '', false )`

Under any full-page cache, a form rendered on page A and served from cache on page
B carries **A's** values. Concretely:

* A form in a cached header/footer/popup template reused site-wide reports the
  **wrong `page_title`** in every notification email and every submissions row.
  This is the symptom already observed.
* `is_singular()` at render time also decides whether `queried_id` is emitted
  **at all**. A form cached from an archive omits it, and the handler silently
  falls back to `post_id` (ajax-handler.php:70).
* `queried_id` drives `switch_to_post()`, which resolves dynamic tags inside the
  form's own settings (ajax-handler.php:74 → :106). A stale `queried_id` means a
  dynamic `email_to`, `email_subject` or `redirect_to` resolves against the wrong
  post. **Mitigating fact: no form on this site uses a dynamic tag in its
  settings, so this failure mode is latent, not active.**

**`FormWidget` already reduces the blast radius** by declaring
`is_dynamic_content(): false` exactly as Pro does — which keeps the element out of
Elementor's own element cache (`_element_cache`). That does nothing about an
external page cache.

**Fix in the rebuild:** derive `page_title` and `page_url` server-side from the
JS-supplied `referrer` URL (cross-checked against `HTTP_REFERER`), not from a
cached hidden input; and validate `queried_id` against that URL before letting it
steer `switch_to_post()`.

---

## 8. What breaks the moment Pro is deactivated, in order of severity

**1 — CATASTROPHIC. The endpoint disappears; every submission returns `400`.**
`wp_ajax_[nopriv_]elementor_pro_forms_send_form` is registered only by
`Ajax_Handler::__construct` (ajax-handler.php:307-308), reached only from
Pro `modules/forms/module.php:304-305`. `admin-ajax.php` finds no hook, returns
`0` with HTTP 400. The JS error path (form bundle:257-265) shows the visitor
jQuery's `"error"` / `"parsererror"` string. **Silent, total data loss with a
meaningless message.** This is *the* reason §9 must land before the cut-over.

**2 — CATASTROPHIC (already solved). The `form` widget stops rendering.**
`Form` and `Login` are Pro widgets (module.php:42-53); without Pro, Elementor
renders nothing for `widgetType: "form"` — no fields, no button, no hidden inputs.
**`FormWidget.php` closes this one.** Note it is upstream of everything else in
this list: without the markup, none of the rest can even be attempted.

**3 — HIGH. The frontend JS handles vanish.**
* `elementor-pro-frontend` → `assets/js/frontend.min.js` (Pro plugin.php:230-236),
  which prints `ElementorProFrontendConfig` (:282-286) containing `ajaxurl`.
* `pro-elements-handlers` → `assets/js/elements-handlers.min.js`, dep
  `elementor-frontend` (plugin.php:301-309), enqueued at plugin.php:240.
* `elementor-pro-webpack-runtime` → `assets/js/webpack-pro.runtime.min.js`
  (plugin.php:293-299), which resolves the lazy chunk
  `assets/js/form.<hash>.bundle.min.js`.
* `elements-handlers.js:458` attaches `form-steps`, `form-sender`, `form-redirect`,
  `fields/date`, `recaptcha`, `fields/time` to the `form` widget (and :459 the
  first three to `subscribe`, :1482 the popup module's `forms-action`).

Without them: no AJAX submit, no multi-step, no redirect-on-success, no
client-side file-size check, no flatpickr pickers. A plain `method="post"` form
would full-page-POST to itself and lose everything. **This is the largest single
piece of work in §9.**

**4 — HIGH. reCAPTCHA dies in both directions.** The handlers are Pro components
(module.php:270-271): the `recaptcha` / `recaptcha_v3` field types disappear from
the type list, `elementor-recaptcha_v3-api` is never registered
(recaptcha-handler.php:101-105), and the verification hook is never added (:293).
**7 of 9 forms.** `FormWidget` restores the field type and the markup; only the
verification is outstanding. The options survive in `wp_options` (§5.1).

**5 — HIGH. Submissions stop being recorded; the tables remain.**
Component: Pro `modules/forms/submissions/component.php`,
`NAME = 'form-submissions'` (:24), `PAGE_ID = 'e-form-submissions'` (:25), gated
in module.php:279-285.

Table names — `submissions/database/query.php:25-27`, :900-902:

| Constant | Table | Live rows |
|---|---|---|
| `Query::E_SUBMISSIONS` | `wp_e_submissions` | **317** |
| `Query::E_SUBMISSIONS_VALUES` | `wp_e_submissions_values` | 2102 |
| `Query::E_SUBMISSIONS_ACTIONS_LOG` | `wp_e_submissions_actions_log` | 317 |

Schema at `submissions/database/migrations/initial.php:13-96`, plus
`referer_title varchar(300)` from `migrations/referer-extra.php:11`.

**The tables and all 317 submissions survive deactivation.** What is lost is the
reader (admin page `?page=e-form-submissions`, component.php:78-86; REST
controllers :161-162; the React app `form-submission-admin` :116-129), the GDPR
exporter/eraser (:164), the trash sweep (:177-179) and CSV export
(`submissions/export/csv-export.php`). And critically **`save-to-database` stops
running**, so new submissions are not recorded even if email works.

**6 — MEDIUM. All submit actions disappear.**
Registrar: `registrars/form-actions-registrar.php:74-87` —
`email, email2, redirect, webhook, mailchimp, drip, activecampaign, getresponse,
convertkit, mailerlite, slack, discord`, plus `save-to-database` (Submissions),
`popup` (Pro `modules/popup/form-action.php:13-21`) and conditionally
`mailpoet`/`mailpoet3` (:141-149). **Only `email` and `save-to-database` are
needed to reach parity with what this site uses.**

**7 — MEDIUM. Field classes and their assets go.**
`registrars/form-fields-registrar.php:33-41` — `Time, Date, Tel, Number,
Acceptance, Upload, Step`. **`FormWidget` reimplements the rendering for all of
them**, so this drops to *validation and processing only*: `tel` pattern checking,
`upload` validation + move-to-disk, `number` bounds, `time` format. Date/Time also
lose their `flatpickr` deps (fields/date.php:12-18, fields/time.php:14-20) —
flatpickr is registered by Elementor free, so re-enqueueing it is one line.

**8 — MEDIUM in general, NON-ISSUE here. Dynamic tags inside form settings.**
`get_settings_for_display()` at ajax-handler.php:106 runs `parse_dynamic_settings`;
without Pro most useful tags cannot resolve. **No form on this site carries a
`__dynamic__` map, so nothing is affected — but state the constraint in whatever
replaces it.**

**9 — LOW. Honeypot** (classes/honeypot-handler.php, module.php:272) — an opt-in
field type, unused here.

**10 — LOW. Editor-side integration panel.** `pro_forms_panel_action_data`
(module.php:177-179) and the `form-fields-repeater` / `forms-fields-map` control
types (module.php:110-113). `FormWidget::get_fields_repeater_control_type()`
already falls back to the core `REPEATER` type when Pro's is unregistered; the
only loss is auto-generated `custom_id` for **newly added** rows.

**11 — LOW. Styles.** The `widget-form` handle (module.php:72-90). **Replaced by
`piecyfer-form` / `assets/css/form.css`.**

---

## 9. Build plan

### 9.1 Suggested layout under `src/Forms/`

```
wp-content/plugins/piecyfer-core/src/Forms/
├── Module.php              Registers everything below. One entry point, called
│                           from Plugin::__construct so the widget file stays
│                           presentation-only.
├── AjaxHandler.php         The endpoint. Nonce, rate limit, widget lookup,
│                           validation orchestration, action dispatch, JSON.
│                           Mirrors Pro ajax-handler.php but with §1.3/§1.4 fixed.
├── FormRecord.php          The value object every action receives. Mirrors Pro
│                           form-record.php (§4) — keep the public method names,
│                           third-party code hooks around this shape.
├── Messages.php            The six message ids + the lookup, with the
│                           server_message / invalid_message mapping FIXED (§2.5).
├── Fields/
│   ├── FieldBase.php       validate() / process() / sanitize() contract.
│   ├── TelField.php        17 fields — regex from tel.php:34.
│   ├── EmailField.php      9 fields — Pro has NO server validation; add is_email().
│   ├── UploadField.php     5 fields — the big one, see §9.3.
│   ├── AcceptanceField.php 3 fields — required-only.
│   ├── NumberField.php     bounds + intval, with the empty-required hole closed.
│   ├── TimeField.php       HH:MM regex.
│   └── DateField.php       Pro has none; add a real one.
├── Actions/
│   ├── ActionBase.php      get_name() / get_label() / run( $record, $handler ).
│   ├── EmailAction.php     §3.4, verbatim behaviour, `email` name.
│   ├── Email2Action.php    extends EmailAction, control-id suffix `_2`.
│   └── (later) RedirectAction, WebhookAction — controls already exist in
│       FormWidget, only run() is missing.
├── Recaptcha/
│   ├── V3Handler.php       §5.2. Reads Pro's option names unchanged.
│   └── V2Handler.php       Only if a v2 field is ever added; none today.
└── Storage/
    └── UploadStore.php     Directory, naming, guard files, retention sweep (§6.7).
```

Registration goes in `Plugin.php` alongside the widget list — **the lead owns that
file; do not edit it from a widget branch.**

### 9.2 Order of work

1. **`AjaxHandler` + `FormRecord` + `Messages` + `EmailAction`.** This alone
   restores every form on the site to working order, because `submit_actions`
   resolves to `['email']` (§3.2). Verify by submitting each of the nine forms
   with Pro's forms module disabled via
   `add_filter( 'piecyfer/forms/use_builtin_fields', '__return_true' )` plus a
   temporary unhook of Pro's ajax action.
2. **Frontend JS.** A single small handler: serialise the form, POST to
   `admin-ajax.php`, render `message` / `errors`, handle `redirect_url`. ~150
   lines against Pro's bundle, because multi-step is not used here. Register as
   `piecyfer-form` and declare it from `FormWidget::get_script_depends()`.
3. **Field validators** — tel, email, acceptance, number, time, date.
4. **`Recaptcha/V3Handler`** — verify the key pair first (§5.1 warning).
5. **`Storage/UploadStore` + `UploadField`** — the security rewrite in §6.7. Treat
   as its own reviewed change, not a bolt-on.
6. **Submissions replacement, or an explicit decision not to have one.** The
   317 existing rows are readable with plain SQL; a minimal admin list table is a
   day's work. Decide before the cut-over whether new submissions are recorded at
   all — silently losing them is the worst outcome.
7. **`Email2Action`, `RedirectAction`, `WebhookAction`** — only if ever needed;
   their controls already exist and their data already round-trips.

### 9.3 Non-negotiables for the new endpoint

* Verify a nonce (§1.3), and rate-limit by IP + form id.
* Assert the located element is actually a `form` widget (§1.4).
* Do not trust `queried_id` (§7) or the `X-Forwarded-For` family (§3.4).
* Keep the JSON response shape byte-compatible (§1.5) — the frontend contract and
  any third-party listener depend on it.
* Keep the `elementor_pro/forms/*` hook names firing where a third party might
  listen. `FormWidget` already fires `pre_render`, `render/item`,
  `render/item/{type}` and `render_field/{type}`. The handler should fire at least
  `elementor_pro/forms/validation`, `elementor_pro/forms/process`,
  `elementor_pro/forms/new_record` and `elementor_pro/forms/mail_sent` — but
  **`mail_sent` after the success check, not before** (§3.4).
* Uploads: CSPRNG names, MIME verification, allow-list only, storage outside the
  web root, retention (§6.7).

### 9.4 Known gaps in `FormWidget.php` to close here

* **Slack and Discord action sections are not reproduced.** No instance has ever
  saved a Slack or Discord setting so nothing is at risk today, but an editor
  cannot configure them either. Add the sections if either is ever wanted.
* **`form-fields-repeater` control type.** Falls back to core `REPEATER` when Pro
  is gone; new rows lose auto-`custom_id`. Registering our own control type is a
  small, self-contained follow-up.
* **`display_percentage`** is reproduced with Pro's upstream bug intact (added to
  the repeater after `get_controls()` was already taken, so it never reaches the
  stack). Fix only in lockstep with upstream, or the control-id parity check
  starts reporting a difference.
* **`*_fields_map` controls** for the six ESP integrations are registered as inert
  `HIDDEN` controls purely so the editor does not drop the saved (empty) arrays.
  If any integration is ever actually wanted, it needs a real control type.
