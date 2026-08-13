/**
 * Maintenance
 * https://wpmaintenancemode.com/
 * (c) WebFactory Ltd, 2026
 */

jQuery(document).ready(function ($) {
  var mtnc_seo_tests = {};
  var keyword_set = false;
  var mtnc_seo_test_running = false;
  var seo_gage = false;
  var theme = mtnc.theme;

  if (theme == null || typeof theme !== "object") {
    theme = {};
    theme.modules = {};
    theme.modules_order = "";
  }

  refresh_modules();

  var module_types = {};
  jscolor.presets.default = {
    format: "rgba",
    previewSize: 36,
  };

  jscolor.install();

  //Logo
  module_types.logo = {};
  module_types.logo.name = "Logo";
  module_types.logo.type = "logo";
  module_types.logo.groups = {};
  module_types.logo.groups.logo = { name: "Logo", active: true };
  module_types.logo.groups.logo.fields = {};
  module_types.logo.groups.logo.fields.logo = { type: "image", name: "logo", label: "Logo", value: "", class: "", desc: "" };
  module_types.logo.groups.logo.fields.title = { type: "text", name: "title", label: "Logo Title", value: "", class: "full-width", desc: "" };
  module_types.logo.groups.logo.fields.width = { type: "number", name: "width", label: "Width", value: "", class: "number_small", units: { px: "px", percent: "%", em: "em" }, unit_value: "px", desc: "Leave blank for auto" };
  module_types.logo.groups.logo.fields.height = { type: "number", name: "height", label: "Height", value: "", class: "number_small", units: { px: "px", percent: "%", em: "em" }, unit_value: "px", desc: "Leave blank for auto" };

  //Header
  module_types.header = {};
  module_types.header.name = "Header";
  module_types.header.type = "header";
  module_types.header.groups = {};
  module_types.header.groups.header = { name: "Header", active: true };
  module_types.header.groups.header.fields = {};
  module_types.header.groups.header.fields.text = { type: "text", name: "text", label: "Text", value: "Maintenance", class: "full-width", desc: "" };
  module_types.header.groups.header.fields.text_align = { type: "radio", name: "text_align", label: "Text Align", class: "", values: { left: "Left", center: "Center", right: "Right" } };
  module_types.header.groups.header.fields.font_size = { type: "range", name: "font_size", label: "Font Size", value: "30", min: 6, max: 120, class: "", units: { px: "px", pt: "pt", em: "em" }, unit_value: "px" };
  module_types.header.groups.header.fields.color = { type: "color", name: "text_color", label: "Color", value: "#000000", desc: "" };
  module_types.header.groups.header.fields.font = { type: "font", name: "font", label: "Font", value: "", class: "full-width", desc: "" };
  module_types.header.groups.header.fields.line_height = { type: "number", name: "line_height", label: "Line Height", value: "1", units: { px: "px", percent: "%", em: "em" }, unit_value: "em", class: "number_small", desc: "" };

  //Content
  module_types.content = {};
  module_types.content.name = "Content";
  module_types.content.type = "content";
  module_types.content.groups = {};
  module_types.content.groups.col1 = { name: "Content", active: true };
  module_types.content.groups.col1.fields = {};
  module_types.content.groups.col1.fields.text = { type: "textarea", name: "text", label: "Text", value: "Maintenance", class: "full-width", desc: "" };
  module_types.content.groups.col1.fields.font_size = { type: "number", name: "font_size", label: "Font Size", value: "14", class: "number_small", units: { px: "px", pt: "pt", em: "em" }, unit_value: "px" };
  module_types.content.groups.col1.fields.color = { type: "color", name: "text_color", label: "Color", value: "#000000", desc: "" };
  module_types.content.groups.col1.fields.font = { type: "font", name: "font", label: "Font", value: "", class: "full-width", desc: "" };
  module_types.content.groups.col1.fields.line_height = { type: "number", name: "line_height", label: "Line Height", value: "1", units: { px: "px", percent: "%", em: "em" }, unit_value: "em", class: "number_small", desc: "" };

  //Footer
  module_types.footer = { ...module_types.content }; //Basically the same as content
  module_types.footer.name = "Footer";
  module_types.footer.type = "footer";
  module_types.content.groups.col1.name = "Footer";
  module_types.content.groups.col1.active = true;

  for (mid in module_types) {
    module_types[mid].groups.layout = { name: "Layout" };
    module_types[mid].groups.layout.fields = {};

    module_types[mid].groups.layout.fields.padding_label = { type: "label", name: "padding_label", label: "Padding" };
    module_types[mid].groups.layout.fields.padding_top = { type: "number", name: "padding_top", label: "Top", image_label: "MTNCURL/img/icons/padding_top.png", value: "10", class: "number_small", units: { px: "px", percent: "%", em: "em" }, unit_value: "px", wrapper_class: "col-4" };
    module_types[mid].groups.layout.fields.padding_bottom = { type: "number", name: "padding_bottom", label: "Bottom", image_label: "MTNCURL/img/icons/padding_bottom.png", value: "10", class: "number_small", units: { px: "px", percent: "%", em: "em" }, unit_value: "px", wrapper_class: "col-4" };
    module_types[mid].groups.layout.fields.padding_left = { type: "number", name: "padding_left", label: "Left", image_label: "MTNCURL/img/icons/padding_left.png", value: "0", class: "number_small", units: { px: "px", percent: "%", em: "em" }, unit_value: "px", wrapper_class: "col-4" };
    module_types[mid].groups.layout.fields.padding_right = { type: "number", name: "padding_right", label: "Right", image_label: "MTNCURL/img/icons/padding_right.png", value: "0", class: "number_small", units: { px: "px", percent: "%", em: "em" }, unit_value: "px", wrapper_class: "col-4" };

    module_types[mid].groups.layout.fields.margin_label = { type: "label", name: "margin_label", label: "Margin" };
    module_types[mid].groups.layout.fields.margin_top = { type: "number", name: "margin_top", label: "Top", image_label: "MTNCURL/img/icons/margin_top.png", value: "0", class: "number_small", units: { px: "px", percent: "%", em: "em" }, unit_value: "px", wrapper_class: "col-4" };
    module_types[mid].groups.layout.fields.margin_bottom = { type: "number", name: "margin_bottom", label: "Bottom", image_label: "MTNCURL/img/icons/margin_bottom.png", value: "0", class: "number_small", units: { px: "px", percent: "%", em: "em" }, unit_value: "px", wrapper_class: "col-4" };
    module_types[mid].groups.layout.fields.margin_left = { type: "number", name: "margin_left", label: "Left", image_label: "MTNCURL/img/icons/margin_left.png", value: "0", class: "number_small", units: { px: "px", percent: "%", em: "em" }, unit_value: "px", wrapper_class: "col-4" };
    module_types[mid].groups.layout.fields.margin_right = { type: "number", name: "margin_right", label: "Right", image_label: "MTNCURL/img/icons/margin_right.png", value: "0", class: "number_small", units: { px: "px", percent: "%", em: "em" }, unit_value: "px", wrapper_class: "col-4" };

    module_types[mid].groups.layout.fields.background_label = { type: "label", name: "background_label", label: "Background" };
    module_types[mid].groups.layout.fields.background_color = { type: "color", name: "background_color", nolabel: true, value: "rgba(255,255,255,0)", desc: "" };
    module_types[mid].groups.layout.fields.background = { type: "image", name: "background", nolabel: true, value: "", class: "", desc: "" };
  }

  $('#mtnc-pro-dialog').dialog({
    dialogClass: 'wp-dialog mtnc-pro-dialog',
    modal: true,
    resizable: false,
    width: 850,
    height: 'auto',
    show: 'fade',
    hide: 'fade',
    close: function (event, ui) {},
    open: function (event, ui) {
      $(this).siblings().find('span.ui-dialog-title').html('Maintenance PRO is here!');
      maintenance_fix_dialog_close(event, ui);
    },
    autoOpen: false,
    closeOnEscape: true,
  });

  function open_upsell(feature) {
    feature = clean_feature(feature);

    $('#mtnc-pro-dialog').dialog('open');

    $('#mtnc-pro-table .button-buy, .link-buy').each(function (ind, el) {
      tmp = $(el).data('href-org');
      tmp = tmp.replace('pricing-table', feature);
      $(el).attr('href', tmp);
    });
  } // open_upsell

  // tabs
  var $state = localStorage.getItem("mtnc_menu");

  if (window.location.hash) {
    if (window.location.hash == '#open-pro-dialog') {
        open_upsell('url-hash');
        window.location.hash = '';
    } else {
        $('a[href="' + window.location.hash + '"]')
        .parents("li")
        .addClass("active");
        $(window.location.hash).show();
        history.replaceState({}, document.title, window.location.href.split("#")[0]);
    }
  } else {
    if ($state) {
      if ($state.indexOf("-") != -1) {
        state = $state.split("-");

        $(".mtnc-main-menu li").removeClass("active");
        $(".mtnc-main-menu li a").removeClass("active-secondary");
        $('a[href="' + state[0] + '"]')
          .parent("li")
          .addClass("active");
        $('a[href="' + $state + '"]').addClass("active-secondary");
        $($state).show();
      } else {
        $(".mtnc-main-menu li").removeClass("active");
        $('a[href="' + $state + '"]')
          .parent("li")
          .addClass("active");
        $($state).show();
      }
    } else {
      $(".mtnc-main-menu li:first").addClass("active");
      $(".mtnc-tab:first").show();
    }
  }

  // backup
  if ($(".mtnc-main-menu li.active").length == 0) {
    $(".mtnc-main-menu li:first").addClass("active");
    $(".mtnc-tab:first").show();
  }
  $(".mtnc-tab-first").hide();

  $(".mtnc-main-menu li a").click(function (e) {
    if($(this).hasClass('open-beacon')){
        return true;
    }

    if($(this).hasClass('open-pro-dialog')){
        return true;
    }
    e.preventDefault();

    var $selector = $(this);
    var $tab = $selector.attr("href");

    if ($selector.hasClass("parent-menu")) {
      $(this).siblings(".mtnc-submenu").find("a:first-child").trigger("click");
      return false;
    }

    localStorage.removeItem("mtnc_menu");

    $(".mtnc-main-menu li").removeClass("active");
    $(".mtnc-main-menu li a").removeClass("active-secondary");
    $selector.parents("li").addClass("active");
    $selector.addClass("active-secondary");

    $(".mtnc-tab").hide();
    $($tab).show();
    $state = $tab;

    localStorage.setItem("mtnc_menu", $tab);
  });

  $(".mtnc-mobile-menu a").click(function () {
    $(".mtnc-main-menu").slideToggle();
  });

  $("body").on("click", ".mtnc-change-tab", function (e) {
    e.preventDefault();
    tab_name = $(this).attr("href");
    $('.mtnc-main-menu li a[href="' + tab_name + '"]').trigger("click");
    window.scrollTo(0, 0);
    return false;
  });

  $(window).on("hashchange", function (e) {
    e.preventDefault();
    tab_name = window.location.hash;
    window.location.hash = "";
    $('.mtnc-main-menu li a[href="' + tab_name + '"]').trigger("click");
    window.scrollTo(0, 0);
    return false;
  });

  // tabs

  // sortable
  sortable = $("#mtnc-theme-builder-modules")
    .sortable({
      animation: 150,
      connectWith: "#mtnc-theme-builder-layout",
      dataIdAttr: "data-id",
      forcePlaceholderSize: true,
      placeholder: "module-placeholder",
      items: "li.available-module",
      start: function (event, ui) {
        $("#mtnc-theme-builder-layout li").addClass("drag-action");
      },
      stop: function (event, ui) {
        $("#mtnc-theme-builder-layout li").removeClass("drag-action");
      },
      create: function (event, ui) {
        $(".mtnc-theme-builder").removeClass("empty");
        if (!$("#mtnc-theme-builder-layout li").length) {
          $("#mtnc-theme-builder-layout").addClass("empty");
        }
        if (!$("#mtnc-theme-builder-modules li").length) {
          $("#mtnc-theme-builder-modules").addClass("empty");
        }
      },
      helper: function (e, li) {
        copyHelper = li.clone().insertAfter(li);
        return li.clone();
      },
      update: function (event, ui) {
        $(".mtnc-theme-builder").removeClass("empty");
        if (!$("#mtnc-theme-builder-layout li").length) {
          $("#mtnc-theme-builder-layout").addClass("empty");
        }
        if (!$("#mtnc-theme-builder-modules li").length) {
          $("#mtnc-theme-builder-modules").addClass("empty");
        }
        if (this.id != "mtnc-theme-builder-layout") {
          return;
        }
        order = $(this).sortable("toArray", { attribute: "data-id" });
      },
    })
    .disableSelection();

  $("#mtnc-theme-builder-layout").sortable({
    receive: function (e, ui) {
      copyHelper = null;
      var module_type = ui.item.attr("data-type");
      ui.item.attr("id", module_type + "-" + get_random_id());
      edit_module($("#" + ui.item.attr("id")));
    },
    update: function (e, ui) {
      $(".mtnc-theme-builder").removeClass("empty");
      if (!$("#mtnc-theme-builder-layout li").length) {
        $("#mtnc-theme-builder-layout").addClass("empty");
      }
      theme.modules_order = {};
      $("#mtnc-theme-builder-layout li").each(function () {
        theme.modules_order[$(this).attr("id")] = $(this).attr("id");
      });
    },
  });

  // Module Clone
  $("#mtnc-theme-builder-layout").on("click", ".mtnc-clone-module", function (e) {
    e.preventDefault();
    $module = $(this).parents("li");
    module_id = $module.attr("id");

    $new_module = $module.clone().insertAfter($module);
    new_module_id = $module.data("type") + "-" + get_random_id();
    $new_module.attr("id", new_module_id);
    theme.modules[new_module_id] = $.extend(true, {}, theme.modules[module_id]);

    theme.modules_order = {};
    $("#mtnc-theme-builder-layout li").each(function () {
      theme.modules_order[$(this).attr("id")] = $(this).attr("id");
    });

    edit_module($new_module);
  });

  //Remove Module
  $("#mtnc-theme-builder-layout").on("click", ".mtnc-remove-module", function (e) {
    e.preventDefault();
    module = $(this).parents("li");
    module_id = module.attr("id");
    delete theme.modules[module_id];
    refresh_modules();
    if (!$("#mtnc-theme-builder-layout li").length) {
      $("#mtnc-theme-builder-layout").addClass("empty");
    }
    $(".mtnc-theme-builder").sortable("refresh").trigger("update");
  });

  //Edut Module
  $("#mtnc-theme-builder-layout").on("click", ".mtnc-edit-module", function (e) {
    e.preventDefault();
    module = $(this).parents("li");
    edit_module(module);
  });

  $("#header-status").on("click", function (e) {
    if ($("#mtnc_status").val() == "1") {
      $("#mtnc_status").val("0");
      $("#header-status .mtnc-status-wrapper").removeClass("on").addClass("off");
    } else {
      $("#mtnc_status").val("1");
      $("#header-status .mtnc-status-wrapper").removeClass("off").addClass("on");
    }
    save_ajax();
  });

  $(".mtnc_submit").on("click", function (e) {
    e.preventDefault();
    save_ajax();
    return false;
    safe_refresh = true;
    return true;
  });

  // Social Icons
  var icm_icons = {
    "Social and Networking": [57694, 57700, 57701, 57702, 57703, 57704, 57705, 57706, 57707, 57709, 57710, 57711, 57717, 57718, 57719, 57736, 57737, 57738, 57739, 57740, 57741, 57742, 57746, 57747, 57748, 57755, 57756, 57758, 57759, 57760, 57761, 57763, 57764, 57765, 57766, 57767, 57776],
    "Web Applications": [57436, 57437, 57438, 57439, 57524, 57525, 57526, 57527, 57528, 57531, 57532, 57533, 57534, 57535, 57536, 57537, 57541, 57545, 57691, 57692],
    "Business Icons": [57347, 57348, 57375, 57376, 57377, 57379, 57403, 57406, 57432, 57433, 57434, 57435, 57450, 57453, 57456, 57458, 57460, 57461, 57463],
    eCommerce: [57392, 57397, 57398, 57399, 57402],
    "Currency Icons": [],
    "Form Control Icons": [57383, 57384, 57385, 57386, 57387, 57388, 57484, 57594, 57595, 57600, 57603, 57604, 57659, 57660, 57693],
    "User Action & Text Editor": [57442, 57443, 57444, 57445, 57446, 57447, 57472, 57473, 57474, 57475, 57476, 57477, 57539, 57662, 57668, 57669, 57670, 57671, 57674, 57675, 57688, 57689],
    "Charts and Codes": [57493],
    Attentive: [57543, 57588, 57590, 57591, 57592, 57593, 57596],
    "Multimedia Icons": [57356, 57357, 57362, 57363, 57448, 57485, 57547, 57548, 57549, 57605, 57606, 57609, 57610, 57611, 57614, 57617, 57618, 57620, 57621, 57622, 57623, 57624, 57625, 57626],
    "Location and Contact": [57344, 57345, 57346, 57404, 57405, 57408, 57410, 57411, 57413, 57414, 57540],
    "Date and Time": [57415, 57416, 57417, 57421, 57422, 57423],
    Devices: [57359, 57361, 57364, 57425, 57426, 57430],
    Tools: [57349, 57350, 57352, 57355, 57365, 57478, 57479, 57480, 57481, 57482, 57483, 57486, 57487, 57488, 57663, 57664],
    Brands: [57743, 57750, 57751, 57752, 57753, 57754, 57757, 57773, 57774, 57775, 57789, 57790, 57792, 57793],
    "Files & Documents": [57378, 57380, 57381, 57382, 57390, 57391, 57778, 57779, 57780, 57781, 57782, 57783, 57784, 57785, 57786, 57787],
    "Like & Dislike Icons": [57542, 57544, 57550, 57551, 57552, 57553, 57554, 57555, 57556, 57557],
    Emoticons: [57558, 57559, 57560, 57561, 57562, 57563, 57564, 57565, 57566, 57567, 57568, 57569, 57570, 57571, 57572, 57573, 57574, 57575, 57576, 57577, 57578, 57579, 57580, 57581, 57582, 57583],
    "Directional Icons": [57584, 57585, 57586, 57587, 57631, 57632, 57633, 57634, 57635, 57636, 57637, 57638, 57639, 57640, 57641, 57642, 57643, 57644, 57645, 57646, 57647, 57648, 57649, 57650, 57651, 57652, 57653, 57654],
    "Other Icons": [
      57351, 57353, 57354, 57358, 57360, 57366, 57367, 57368, 57369, 57370, 57371, 57372, 57373, 57374, 57389, 57393, 57394, 57395, 57396, 57400, 57401, 57407, 57409, 57412, 57418, 57419, 57420, 57424, 57427, 57428, 57429, 57431, 57440, 57441, 57449, 57451, 57452, 57454, 57455, 57457, 57459, 57462, 57464, 57465, 57466, 57467, 57468, 57469, 57470, 57471, 57489, 57490, 57491, 57492, 57494, 57495, 57496, 57497, 57498, 57499, 57500, 57501, 57502, 57503, 57504, 57505, 57506, 57507, 57508, 57509,
      57510, 57511, 57512, 57513, 57514, 57515, 57516, 57517, 57518, 57519, 57520, 57521, 57522, 57523, 57529, 57530, 57538, 57546, 57589, 57597, 57598, 57599, 57601, 57602, 57607, 57608, 57612, 57613, 57615, 57616, 57619, 57627, 57628, 57629, 57630, 57655, 57656, 57657, 57658, 57661, 57665, 57666, 57667, 57672, 57673, 57676, 57677, 57678, 57679, 57680, 57681, 57682, 57683, 57684, 57685, 57686, 57687, 57690, 57695, 57696, 57697, 57698, 57699, 57708, 57712, 57713, 57714, 57715, 57716, 57720,
      57721, 57722, 57723, 57724, 57725, 57726, 57727, 57728, 57729, 57730, 57731, 57732, 57733, 57734, 57735, 57744, 57745, 57749, 57762, 57768, 57769, 57770, 57771, 57772, 57777, 57788, 57791, 57794,
    ],
  };

  var icm_icon_search = {
    "Web Applications": ["Box add", "Box remove", "Download", "Upload", "List", "List 2", "Numbered list", "Menu", "Menu 2", "Cloud download", "Cloud upload", "Download 2", "Upload 2", "Download 3", "Upload 3", "Globe", "Attachment", "Bookmark", "Embed", "Code"],
    "Business Icons": ["Office", "Newspaper", "Book", "Books", "Library", "Profile", "Support", "Address book", "Cabinet", "Drawer", "Drawer 2", "Drawer 3", "Bubble", "Bubble 2", "User", "User 2", "User 3", "User 4", "Busy"],
    eCommerce: ["Tag", "Cart", "Cart 2", "Cart 3", "Calculate"],
    "Currency Icons": [],
    "Form Control Icons": ["Copy", "Copy 2", "Copy 3", "Paste", "Paste 2", "Paste 3", "Settings", "Cancel circle", "Checkmark circle", "Spell check", "Enter", "Exit", "Radio checked", "Radio unchecked", "Console"],
    "User Action & Text Editor": ["Undo", "Redo", "Flip", "Flip 2", "Undo 2", "Redo 2", "Zoomin", "Zoomout", "Expand", "Contract", "Expand 2", "Contract 2", "Link", "Scissors", "Bold", "Underline", "Italic", "Strikethrough", "Table", "Table 2", "Indent increase", "Indent decrease"],
    "Charts and Codes": ["Pie"],
    Attentive: ["Eye blocked", "Warning", "Question", "Info", "Info 2", "Blocked", "Spam"],
    "Multimedia Icons": ["Image", "Image 2", "Play", "Film", "Forward", "Equalizer", "Brightness medium", "Brightness contrast", "Contrast", "Play 2", "Pause", "Forward 2", "Play 3", "Pause 2", "Forward 3", "Previous", "Next", "Volume high", "Volume medium", "Volume low", "Volume mute", "Volume mute 2", "Volume increase", "Volume decrease"],
    "Location and Contact": ["Home", "Home 2", "Home 3", "Phone", "Phone hang up", "Envelope", "Location", "Location 2", "Map", "Map 2", "Flag"],
    "Date and Time": ["History", "Clock", "Clock 2", "Stopwatch", "Calendar", "Calendar 2"],
    Devices: ["Camera", "Headphones", "Camera 2", "Keyboard", "Screen", "Tablet"],
    Tools: ["Pencil", "Pencil 2", "Pen", "Paint format", "Dice", "Key", "Key 2", "Lock", "Lock 2", "Unlocked", "Wrench", "Cog", "Cogs", "Cog 2", "Filter", "Filter 2"],
    "Social and Networking": [
      "Share",
      "Googleplus",
      "Googleplus 2",
      "Googleplus 3",
      "Googleplus 4",
      "Google drive",
      "Facebook",
      "Facebook 2",
      "Facebook 3",
      "Twitter",
      "Twitter 2",
      "Twitter 3",
      "Vimeo",
      "Vimeo 2",
      "Vimeo 3",
      "Github",
      "Github 2",
      "Github 3",
      "Github 4",
      "Github 5",
      "Wordpress",
      "Wordpress 2",
      "Tumblr",
      "Tumblr 2",
      "Yahoo",
      "Soundcloud",
      "Soundcloud 2",
      "Reddit",
      "Linkedin",
      "Lastfm",
      "Lastfm 2",
      "Stumbleupon",
      "Stumbleupon 2",
      "Stackoverflow",
      "Pinterest",
      "Pinterest 2",
      "Yelp",
    ],
    Brands: ["Joomla", "Apple", "Finder", "Android", "Windows", "Windows 8", "Skype", "Paypal", "Paypal 2", "Paypal 3", "Chrome", "Firefox", "Opera", "Safari"],
    "Files & Documents": ["File", "File 2", "File 3", "File 4", "Folder", "Folder open", "File pdf", "File openoffice", "File word", "File excel", "File zip", "File powerpoint", "File xml", "File css", "Html 5", "Html 52"],
    "Like & Dislike Icons": ["Eye", "Eye 2", "Star", "Star 2", "Star 3", "Heart", "Heart 2", "Heart broken", "Thumbs up", "Thumbs up 2"],
    Emoticons: ["Happy", "Happy 2", "Smiley", "Smiley 2", "Tongue", "Tongue 2", "Sad", "Sad 2", "Wink", "Wink 2", "Grin", "Grin 2", "Cool", "Cool 2", "Angry", "Angry 2", "Evil", "Evil 2", "Shocked", "Shocked 2", "Confused", "Confused 2", "Neutral", "Neutral 2", "Wondering", "Wondering 2"],
    "Directional Icons": ["Point up", "Point right", "Point down", "Point left", "Arrow up left", "Arrow up", "Arrow up right", "Arrow right", "Arrow down right", "Arrow down", "Arrow down left", "Arrow left", "Arrow up left 2", "Arrow up 2", "Arrow up right 2", "Arrow right 2", "Arrow down right 2", "Arrow down 2", "Arrow down left 2", "Arrow left 2", "Arrow up left 3", "Arrow up 3", "Arrow up right 3", "Arrow right 3", "Arrow down right 3", "Arrow down 3", "Arrow down left 3", "Arrow left 3"],
    "Other Icons": [
      "Quill",
      "Blog",
      "Droplet",
      "Images",
      "Music",
      "Pacman",
      "Spades",
      "Clubs",
      "Diamonds",
      "Pawn",
      "Bullhorn",
      "Connection",
      "Podcast",
      "Feed",
      "Stack",
      "Tags",
      "Barcode",
      "Qrcode",
      "Ticket",
      "Coin",
      "Credit",
      "Notebook",
      "Pushpin",
      "Compass",
      "Alarm",
      "Alarm 2",
      "Bell",
      "Print",
      "Laptop",
      "Mobile",
      "Mobile 2",
      "Tv",
      "Disk",
      "Storage",
      "Reply",
      "Bubbles",
      "Bubbles 2",
      "Bubbles 3",
      "Bubbles 4",
      "Users",
      "Users 2",
      "Quotes left",
      "Spinner",
      "Spinner 2",
      "Spinner 3",
      "Spinner 4",
      "Spinner 5",
      "Spinner 6",
      "Binoculars",
      "Search",
      "Hammer",
      "Wand",
      "Aid",
      "Bug",
      "Stats",
      "Bars",
      "Bars 2",
      "Gift",
      "Trophy",
      "Glass",
      "Mug",
      "Food",
      "Leaf",
      "Rocket",
      "Meter",
      "Meter 2",
      "Dashboard",
      "Hammer 2",
      "Fire",
      "Lab",
      "Magnet",
      "Remove",
      "Remove 2",
      "Briefcase",
      "Airplane",
      "Truck",
      "Road",
      "Accessibility",
      "Target",
      "Shield",
      "Lightning",
      "Switch",
      "Powercord",
      "Signup",
      "Tree",
      "Cloud",
      "Earth",
      "Bookmarks",
      "Notification",
      "Close",
      "Checkmark",
      "Checkmark 2",
      "Minus",
      "Plus",
      "Stop",
      "Backward",
      "Stop 2",
      "Backward 2",
      "First",
      "Last",
      "Eject",
      "Loop",
      "Loop 2",
      "Loop 3",
      "Shuffle",
      "Tab",
      "Checkbox checked",
      "Checkbox unchecked",
      "Checkbox partial",
      "Crop",
      "Font",
      "Text height",
      "Text width",
      "Omega",
      "Sigma",
      "Insert template",
      "Pilcrow",
      "Lefttoright",
      "Righttoleft",
      "Paragraph left",
      "Paragraph center",
      "Paragraph right",
      "Paragraph justify",
      "Paragraph left 2",
      "Paragraph center 2",
      "Paragraph right 2",
      "Paragraph justify 2",
      "Newtab",
      "Mail",
      "Mail 2",
      "Mail 3",
      "Mail 4",
      "Google",
      "Instagram",
      "Feed 2",
      "Feed 3",
      "Feed 4",
      "Youtube",
      "Youtube 2",
      "Lanyrd",
      "Flickr",
      "Flickr 2",
      "Flickr 3",
      "Flickr 4",
      "Picassa",
      "Picassa 2",
      "Dribbble",
      "Dribbble 2",
      "Dribbble 3",
      "Forrst",
      "Forrst 2",
      "Deviantart",
      "Deviantart 2",
      "Steam",
      "Steam 2",
      "Blogger",
      "Blogger 2",
      "Tux",
      "Delicious",
      "Xing",
      "Xing 2",
      "Flattr",
      "Foursquare",
      "Foursquare 2",
      "Libreoffice",
      "Css 3",
      "IE",
      "IcoMoon",
    ],
  };

  var social_icons_picker;
  function init_icon_picker() {
    if (social_icons_picker) {
      social_icons_picker.destroyPicker();
    }
    social_icons_picker = $(".social_icon_icon").fontIconPicker({
      source: icm_icons,
      searchSource: icm_icon_search,
      useAttribute: true,
      attributeName: "data-icomoon",
      theme: "fip-bootstrap",
    });
    $(".social_icons_table_rows").sortable({ forcePlaceholderSize: true });
  }

  $("body").on("click", ".social_icons_add_new", function () {
    var social_icon_html = "<tr>";
    social_icon_html += '<td><div class="move_block"><i data-icomoon=""></i></div></td>';
    social_icon_html += '<td><input type="text" class="social_icon_url" placeholder="https://" value="" /></td>';
    social_icon_html += '<td><input type="text" class="social_icon_icon" value="0" /></td>';
    social_icon_html += '<td><button type="button" class="button button-primary"><strong>Delete</strong></button></td>';
    social_icon_html += "</tr>";
    $(".social_icons_table_rows").append(social_icon_html);
    init_icon_picker();
  });

  $("body").on("click", ".social_icon_delete", function () {
    $(this).parents("tr").remove();
  });

  // Open module add/edit swal
  function edit_module(module) {
    mtnc_swal
      .fire({
        title: false,
        html: edit_module_html(module),
        confirmButtonText: "Save changes",
        showCancelButton: true,
        showCloseButton: true,
        className: "mntc-edit-module",
        width: 1000,
        onRender: function () {
          jscolor.install();
          init_icon_picker();
          $(".datepicker").each(function () {
            $(this).AnyTime_noPicker();
            options = { format: $(this).data("format") ? $(this).data("format") : "%Y-%m-%d %H:%i", firstDOW: 1, earliest: $(this).data("earliest") == "now" ? new Date() : "", labelTitle: $(this).data("title") ? $(this).data("title") : "Select a Date &amp; Time" };

            $(this).AnyTime_picker(options);
          });

          $(".summernote").summernote({
            height: 140,
          });
        },
        preConfirm: () => {
          form = mtnc_swal.getContent();
          return edit_module_save(module, form);
        },
      })
      .then(function (result) {
        if (result.dismiss || typeof result.value == "undefined") {
          module_id = module.attr("id");

          if (!theme.modules.hasOwnProperty(module_id)) {
            $("#" + module_id).remove();
            $(".mtnc-theme-builder").sortable("refresh").trigger("update");
            if ($("#mtnc-theme-builder-layout li").length == 0) {
              $("#mtnc-theme-builder-layout").addClass("empty");
            } else {
              $("#mtnc-theme-builder-layout").removeClass("empty");
            }
          }
        }
      });
  } // edit_module

  // Switch tabs in edit popup
  $("body").on("click", ".mtnc-swal-tabs li", function () {
    var tab = $(this).data("tab");
    $(".mtnc-swal-tabs li").removeClass("active");
    $(this).addClass("active");
    $(".mtnc-swal-tab").hide();
    $('.mtnc-swal-tab[data-tab="' + tab + '"]').show();
  });

  // Refresh modules list
  function refresh_modules() {
    html = "";
    for (id in theme.modules_order) {
      if (theme.modules[theme.modules_order[id]]) {
        html += print_module_html(theme.modules_order[id], theme.modules[theme.modules_order[id]]);
      } else {
        delete theme.modules_order[id];
      }
    }

    $("#mtnc-theme-builder-modules").find("li").removeClass("module-used");


    $("#mtnc-theme-builder-layout").html(html);

    if ($("#mtnc-theme-builder-layout li").length == 0) {
      $("#mtnc-theme-builder-layout").addClass("empty");
    } else {
      $("#mtnc-theme-builder-layout").removeClass("empty");
    }
  } // refresh_modules

  // Build HTML to display in module list
  function print_module_html(module, module_data) {
    html = '<li id="' + module + '" data-type="' + module_data.type + '">';
    html += '<img src="' + mtnc.url + "/img/modules/" + module_data.type + '.png" title="Drag to rearrange the module on maintenance page, or move it to inactive modules"><br />';
    html += '<span class="module-name">' + module_data.name + "</span>";
    html += '<div class="module-actions"><span class="module-name">' + module_data.name + "</span>";
    html += '<a title="Drag to rearrange the module on maintenance page, or move it to inactive modules" href="#" class="mtnc-move-module"><span class="dashicons dashicons-move"></span></a>';
    html += '<a title="Edit module" href="#" class="mtnc-edit-module" title="Edit module"><span class="dashicons dashicons-edit"></span></a>';
    html += '<a title="Clone module" href="#" class="mtnc-clone-module" title="Clone module"><span class="dashicons dashicons-admin-page"></span></a>';
    html += '<a href="#" class="mtnc-remove-module" title="Remove module from maintenance page"><span class="dashicons dashicons-trash"></span></a>';
    html += "</li>";

    return html;
  } // print_module_html

  // Build module edit HTML for swal popup
  function edit_module_html($module) {
    module_id = $module.attr("id");
    module_type = $module.data("type");
    var html = "";

    //Print module name
    if (theme.modules.hasOwnProperty(module_id)) {
      html += "<h2>" + theme.modules[module_id].name + "</h2>";
    } else {
      module_type = $module.data("type");
      html += "<h2>" + module_types[module_type].name + "</h2>";
    }

    //Generate tab buttons
    html += '<ul class="mtnc-swal-tabs">';
    for (group in module_types[module_type].groups) {
      var tab_classes = [];
      if (module_types[module_type].groups[group].active) {
        tab_classes.push("active");
      }

      if (module_types[module_type].groups[group].class) {
        tab_classes.push(module_types[module_type].groups[group].class);
      }
      html += '<li data-tab="' + group + '" class="' + tab_classes.join(" ") + '">' + module_types[module_type].groups[group].name + "</li>";
    }
    html += "</ul>";

    //Loop though all groups and create a tab for each group
    for (group in module_types[module_type].groups) {
      html += '<div ssssss class="mtnc-swal-tab ' + module_types[module_type].groups[group].class + '" data-tab="' + group + '" ' + (module_types[module_type].groups[group].active ? "" : 'style="display:none;"') + ">";
      //Loop through each field in the group (we loop though the default field definitions in case any new fields have been added to that group)
      for (field in module_types[module_type].groups[group].fields) {
        //Check if the current module has values for this field or if it's a new field, we use it's defaults
        if (theme.modules.hasOwnProperty(module_id) && theme.modules[module_id].groups.hasOwnProperty(group) && theme.modules[module_id].groups[group].fields.hasOwnProperty(field)) {
          //Set all field properties from the default module definitions in case module definitions have changed (i.e chande field from text to textarea)  but we keep all user set properties
          for (field_property in module_types[module_type].groups[group].fields[field]) {
            //Ignore value properties controlled by the user
            if (field_property != "value" && field_property != "unit_value" && field_property != "icons" && !(field_property = "options" && field == "audience")) {
              theme.modules[module_id].groups[group].fields[field][field_property] = module_types[module_type].groups[group].fields[field][field_property];
            }
          }
          html += get_field_html(theme.modules[module_id].groups[group].fields[field]);
        } else {
          html += get_field_html(module_types[module_type].groups[group].fields[field]);
        }
      }

      html += "</div>";
    }

    html += "</div>";

    return html;
  } // edit_module_html

  // Save module data from swal popup and refresh modules list
  function edit_module_save($module, form) {
    module_id = $module.attr("id");
    module_type = $module.data("type");

    // If module doesn't exist in theme object, add it now
    if (!theme.modules.hasOwnProperty(module_id)) {
      theme.modules[module_id] = $.extend(true, {}, module_types[module_type]);
    }

    //Clone module definitions so we don't change original object
    var module_types_clone = $.extend(true, {}, module_types[module_type]);

    //Go over each field in the module definition in case new fields have been added
    for (group in module_types_clone.groups) {
      for (field in module_types_clone.groups[group].fields) {
        theme.modules[module_id].groups[group].fields[field] = get_field_value(module_types_clone.groups[group].fields[field], form);
      }
    }

    
    refresh_modules();
    return true;
  } // edit_module_save

  // Get field value from swal HTML when saving
  function get_field_value(field, form) {
    switch (field.type) {
      case "text":
      case "number":
      case "image":
      case "color":
      case "select":
      case "select_mc_audience":
      case "date_time":
      case "font":
      case "range":
        field.value = $(form)
          .find("#" + field.name)
          .val();
        if (field.units) {
          field.unit_value = $(form)
            .find("#" + field.name + "_unit")
            .val();
        }
        if (field.type == "select_mc_audience") {
          field.options = {};
          $(form)
            .find("#" + field.name + " option")
            .each(function () {
              field.options[$(this).val()] = $(this).text();
            });
        }

        break;
      case "textarea":
      case "code":
        field.value = $(form)
          .find("#" + field.name)
          .val();
        break;
      case "toggle":
        field.value = $(form)
          .find("#" + field.name)
          .is(":checked");
        break;
      case "multioption":
        for (option in field.values) {
          field.values[option].selected = $(form)
            .find("#" + field.name + "_" + option)
            .is(":checked");
        }
        break;
      case "radio":
        field.value = $(form)
          .find('[name="' + field.name + '"]:checked')
          .val();
        break;
      case "socialicons":
        field.icons = {};
        var icon_index = 0;
        $(form)
          .find(".social_icons_table_rows tr")
          .each(function () {
            field.icons[icon_index] = { icon: $(this).find(".social_icon_icon").val(), url: $(this).find(".social_icon_url").val() };
            icon_index++;
          });
        break;
      default:
        break;
    }

    return field;
  } // get_field_value

  $("#content_overlay").change(function () {
    if ($(this).is(":checked")) {
      $(".overlay_parameters").fadeIn();
    } else {
      $(".overlay_parameters").fadeOut();
    }
  });

  // css and html editor
  function getEditor($editorID, $textareaID, $mode) {
    if ($("#" + $editorID).length > 0) {
      var editor = ace.edit($editorID),
        $textarea = $("#" + $textareaID).hide();

      editor.getSession().setValue($textarea.val());

      editor.getSession().on("change", function () {
        $textarea.val(editor.getSession().getValue());
      });

      editor.getSession().setMode("ace/mode/" + $mode);
      //editor.setTheme( 'ace/theme/xcode' );
      editor.getSession().setUseWrapMode(true);
      editor.getSession().setWrapLimitRange(null, null);
      editor.renderer.setShowPrintMargin(null);

      editor.session.setUseSoftTabs(null);
    }
  }

  getEditor("custom_css_editor", "custom_css", "css");

  // Generate HTML for add/edit for a single field
  function get_field_html(field) {
    html = '<div class="mtnc-swal-field-wrapper ' + (field.hasOwnProperty("wrapper_class") ? field.wrapper_class : "") + '">';

    if (!field.nolabel) {
      if (field.hasOwnProperty("image_label")) {
        html += '<label for="' + field.name + '" class="image_label"><img title="' + field.label + '" src="' + field.image_label.replace("MTNCURL", mtnc.url) + '" /></label> ';
      } else {
        html += '<label for="' + field.name + '" ' + (field.type == "label" ? 'class="full_width_label"' : "") + ">" + field.label + ":</label> ";
      }
      html += '<div class="mtnc-swal-input-wrapper">';
    }

    switch (field.type) {
      case "label":
        html += "";
        break;
      case "text":
        html += '<input id="' + field.name + '" name="' + field.name + '"  type="text" class="' + field.class + '" value="' + field.value + '" />';
        break;
      case "textarea":
        html += '<textarea id="' + field.name + '" class="mtnc_editor summernote ' + field.class + '" style="height:200px; width:100%;">' + field.value + "</textarea>";
        break;
      case "code":
        html += '<textarea id="' + field.name + '" class="mtnc_editor ' + field.class + '" style="height:200px">' + field.value + "</textarea>";
        break;
      case "date_time":
        html += '<input id="' + field.name + '" name="' + field.name + '"  type="text" class="datepicker" value="' + field.value + '" />';
        break;
      case "select":
      case "select_mc_audience":
        var select_options_count = 0;
        var select_options_html = "";
        for (oid in field.options) {
          select_options_html += '<option value="' + oid + '" ' + (oid == field.value ? 'selected="selected"' : "") + ">" + field.options[oid] + "</option>";
          select_options_count++;
        }
        html += '<select id="' + field.name + '" name="' + field.name + '"  class="' + field.class + (select_options_count > 0 ? "disabled" : "") + '">';
        html += select_options_html;
        html += "</select>";
        if (field.type == "select_mc_audience") {
          html += '<div class="mtnc-btn refresh_mailchimp_audience">Refresh Audiences<span class="spinner"></div></span>';
        }
        break;
      case "number":
        html += '<input id="' + field.name + '" name="' + field.name + '"  type="number" class="' + field.class + '" value="' + field.value + '" />';
        if (field.units) {
          html += '<select id="' + field.name + '_unit" name="' + field.name + '_unit"  class="' + field.class + '">';
          for (uid in field.units) {
            html += '<option value="' + uid + '" ' + (field.unit_value == uid ? " selected" : "") + ">" + field.units[uid] + "</option>";
          }
          html += "</select>";
        }
        break;
      case "image":
        html += '<div class="mtnc-image-upload-wrapper">';
        html += '<input type="hidden" name="' + field.name + '" id="' + field.name + '" class="mtnc-image-upload-input" value="' + field.value + '">';

        if (field.value && field.value.length > 0) {
          html += '<div id="' + field.name + '_upload" class="mtnc-image-upload-preview-wrapper" style="background-image:url(' + field.value + ');">';
        } else {
          html += '<div id="' + field.name + '_upload" class="mtnc-image-upload-preview-wrapper"><img src="' + mtnc.url + '/img/icons/image.png" />';
        }
        html += '<button type="button" name="' + field.name + '_upload" id="' + field.name + '_upload" class="button button-primary mtnc-image-upload-button mtnc-free-images" style="margin-top: 4px">Choose an image</button>';
        if (field.value && field.value.length > 0) {
          html += '<button type="button" class="button mtnc-image-upload-remove" style="margin-top: 4px">Remove</button>';
        }
        html += "</div>";
        html += '<div class="mtnc-preview-area">';

        html += "</div>";
        html += "</div>";
        break;
      case "color":
        html += '<input id="' + field.name + '" name="' + field.name + '"  type="text" class="' + field.class + '" data-jscolor="" value="' + field.value + '" />';
        break;
      case "range":
        html += '<div class="range-slider-wrapper">';
        html += '<input id="' + field.name + '" name="' + field.name + '"  type="range" min="' + field.min + '" max="' + field.max + '" class="range-slider ' + field.class + '" value="' + field.value + '" />';
        html += "</div>";
        html += '<span class="range_value" data-unit="' + field.unit + '">' + field.value + "</span>";
        if (field.units) {
          html += '<select id="' + field.name + '_unit" name="' + field.name + '_unit"  class="' + field.class + '">';
          for (uid in field.units) {
            html += '<option value="' + uid + '" ' + (field.unit_value == uid ? " selected" : "") + ">" + field.units[uid] + "</option>";
          }
          html += "</select>";
        } else {
          html += field.unit;
        }
        html += "</span>";
        break;
      case "toggle":
        html += '<div class="toggle-wrapper">';
        html += '<input type="checkbox" id="' + field.name + '" name="' + field.name + '" ' + (field.value == "1" ? 'checked="checked"' : "") + ' value="1">';
        html += '<label for="' + field.name + '" class="toggle"><span class="toggle_handler"></span></label>';
        html += "</div>";
        break;
      case "multioption":
        for (value in field.values) {
          html += '<label class="multioption_label" for="' + field.name + "_" + value + '">' + field.values[value].label;
          html += '<input type="checkbox" id="' + field.name + "_" + value + '" name="' + field.name + "_" + value + '" ' + (field.values[value].selected ? "checked" : "") + ' value="' + value + '" />';
          html += '<span class="multioption_checkmark"></span>';
          html += "</label>";
        }
        break;
      case "radio":
        for (value in field.values) {
          html += '<label class="radio_label" for="' + field.name + "_" + value + '">' + field.values[value];
          html += '<input type="radio" id="' + field.name + "_" + value + '" name="' + field.name + '" ' + (field.value == value ? "checked" : "") + ' value="' + value + '" />';
          html += '<span class="radio_checkmark"></span>';
          html += "</label>";
        }
        break;
      case "font":
        html += '<select id="' + field.name + '" name="' + field.name + '"  class="mtnc-bunny-fonts ' + field.class + '">';
        for (fid in mtnc.fonts) {
          html += '<option value="' + mtnc.fonts[fid] + '" ' + (field.value == mtnc.fonts[fid] ? " selected" : "") + ">" + mtnc.fonts[fid] + "</option>";
        }
        html += "</select>";
        html += '<h3 class="mtnc-font-preview">This is how the content font is going to look!</h3>';
        break;
      case "socialicons":
        html += '<div class="social_icons_list">';
        html += '<table class="table social_icons_table"><thead><tr><th>&nbsp;</th><th style="min-width: 390px;">URL</th><th style="min-width: 100px;">Icon</th><th><button type="button" class="button button-primary social_icons_add_new" class="mtnc-btn">Add new icon</button></th></tr></thead>';
        html += '<tbody class="social_icons_table_rows">';
        for (icon in field.icons) {
          html += "<tr>";
          html += '<td><div class="move_block"><i data-icomoon=""></i></div></td>';
          html += '<td><input type="text" class="social_icon_url" style="width:390px;" placeholder="https://" value="' + field.icons[icon].url + '" /></td>';
          html += '<td><input type="text" class="social_icon_icon" value="' + field.icons[icon].icon + '" /></td>';
          html += '<td><button type="button" class="button button-primary social_icon_delete"><span class="dashicons dashicons-no"></span> Delete</button></td>';
          html += "</tr>";
        }
        html += "</tbody>";
        html += "</table>";
        html += "</div>";
        break;
      default:
        break;
    }

    if (!field.nolabel) {
      if (field.desc) {
        html += '<div class="mtnc-swal-input-description">' + field.desc + "</div>";
      }
      html += "</div>";
    }

    html += "</div>";
    return html;
  } // get_field_html

  $(document).on("click", ".refresh_mailchimp_audience", function (e) {
    e.preventDefault();
    var mailchimp_api_key = $(this).parents(".mtnc-swal-tab").find("#api_key").val();
    $button = $(this);
    $button.addClass("mtnc-btn-loading");

    $.post({
      url: ajaxurl,
      data: {
        action: "mtnc_action",
        _ajax_nonce: mtnc.nonce_action,
        mtnc_action: "refresh_mailchimp_audiences",
        api_key: mailchimp_api_key,
      },
    })
      .always(function (data) {
        $button.removeClass("mtnc-btn-loading");
      })
      .done(function (data) {
        if (data.success) {
          var $el = $button.siblings("select");
          $el.empty(); // remove old options
          var list_count = 0;
          $.each(data.data, function (key, option) {
            list_count++;
            $el.append($("<option></option>").attr("value", option.val).text(option.label));
          });
          if (list_count > 0) {
            $el.removeClass("disabled");
          } else {
            $el.addClass("disabled");
          }
        }
      })
      .fail(function (data) {
        mtnc_swal.fire({ type: "error", title: mtnc.undocumented_error });
      });
  });

  //Google font preview
  $(document).on("change", ".mtnc-bunny-fonts", function () {
    var $font = $(this);
    changeFont($font);
  });

  function changeFont($font) {
    var $fontValue = $font.val();

    reloadFont($fontValue);
    $font.parent().find("h3").css("font-family", $fontValue);
  }

  function reloadFont($fontValue) {
    baseFonts = ["Arial", "Helvetica", "Georgia", "Times New Roman", "Tahoma", "Verdana", "Geneva"];
    if (baseFonts.indexOf($fontValue) != -1) {
      return;
    }

    WebFont.load({
      google: {
        families: [$fontValue],
      },
    });
  }

  $("#background_type")
    .change(function (e) {
      $("#design-background .background-type").hide();
      $("#design-background .background-type-" + $(this).val()).show();
    })
    .trigger("change");

  $("body")
    .on("input", 'input[type="range"]', function (e) {
      $(this).parents(".mtnc-swal-input-wrapper").find(".range_value").html(this.value);
    })
    .trigger("change");

  $("#background_image_filter")
    .on("change", function (e) {
      filter = $(this).val();
      image = $("#background-preview img");
      if (!image.length) {
        return;
      }

      $(image).removeClass();
      $(image).addClass(filter);
    })
    .trigger("change");
  $("#background_video_filter")
    .on("change", function (e) {
      filter = $(this).val();
      video = $("#video-preview");
      video_fallback = $("#video-fallback-preview img");

      $(video).removeClass().addClass(filter);
      $(video_fallback).removeClass().addClass(filter).addClass(filter);
    })
    .trigger("change");

  $("#background_video")
    .on("change keyup click", function (e) {
      video_url = $(this).val();
      preview = $("#video-preview .video-container");

      if (video_url == preview.data("video-id")) {
        return;
      }

      if (video_url) {
        video = '<iframe src="https://www.youtube.com/embed/' + video_url + "?controls=0&amp;showinfo=0&amp;rel=0&amp;autoplay=1&amp;loop=1&amp;mute=1&amp;playlist=" + video_url + '" frameborder="0"></iframe>';
      } else {
        video = "";
      }

      preview.data("video-id", video_url);
      preview.html(video);
    })
    .trigger("change");

  $(document).on("click", ".mtnc-upload", function (e) {

    e.preventDefault();
    getUploader("Select Image", $(this));
  });

  // Removing photo from the canvas and emptying the text field
  $(document).on("click", ".mtnc-remove-image", function (e) {
    e.preventDefault();
    $(this).parent().parent().find("input").val("");
    $(this).parent().parent().find(".mtnc-preview-area").html("Select an image or upload a new one");
    $(this).hide();
  });

  var custom_uploader;


  $(document).on("click", ".mtnc-image-upload-button", function (e) {
    e.preventDefault();
    getUploader("Select Image", $(this));
  });

  $(document).on("click", ".mtnc-image-upload-remove", function (e) {
    e.preventDefault();
    $(this).parents(".mtnc-image-upload-wrapper").children(".mtnc-image-upload-input").val("");
    $(this).parent().css("background-image", "");
    $(this)
      .parent()
      .prepend('<img src="' + mtnc.url + '/img/icons/image.png" />');
    $(this).parent().children(".mtnc-image-upload-remove").remove();
  });

  $("body").on("click", ".media-frame-router .media-router .media-menu-item", function () {
    $(".media-button-select").show();
    $(".mtnc-media-button-select").hide();
  });

  $("body").on("click", ".mtnc-media-button-select", function () {
    $(".mtnc-media-button-select").attr("disabled", "disabled");
    if ($(".media-menu-item.active").hasClass("mtnc-unsplash-images")) {
      var mtnc_unsplash_id = "";
      var image_input_id = $(this).data("id");
      $(".mtnc-unsplash-image-selected").each(function () {
        mtnc_unsplash_id = $(this).data("id");
        mtnc_unsplash_url = $(this).data("url");
        mtnc_unsplash_name = $(this).data("name");
      });

      if (mtnc_unsplash_id != "") {
        $(".media-modal-content .media-frame-content").html('<div class="unsplash-browser"><div class="mtnc-loader"><span class="dashicons dashicons-spin dashicons-update"></span>&nbsp; Downloading image ... </div> </div>');
        $.ajax({
          url: ajaxurl,
          method: "POST",
          crossDomain: true,
          dataType: "json",
          timeout: 300000,
          data: {
            action: "mtnc_editor_unsplash_download",
            image_id: mtnc_unsplash_id,
            image_url: mtnc_unsplash_url,
            image_name: mtnc_unsplash_name,
          },
        })
          .success(function (response) {
            if (response.success) {
              if (response.data) {
                $("#" + image_input_id)
                  .parent()
                  .css("background-image", response.data);
                $("#" + image_input_id)
                  .parent()
                  .find(".mtnc-upload-append")
                  .html('&nbsp;<a href="javascript: void(0);" class="mtnc-remove-image">Remove</a>');
                $("#" + image_input_id).val(response.data);
                $("#" + image_input_id).parents('.mtnc-image-upload-wrapper').children('.mtnc-image-upload-preview-wrapper').css('background-image', "url(" + response.data + ")");
                custom_uploader.close();
              }
            } else {
              $(".unsplash-browser").html(response.data);
              var message = "An error occured downloading the image.";
              if (response.data) {
                message = response.data;
              }
              $(".unsplash-browser").html('<div class="mtnc-loader">' + message + '<br /><span class="mtnc-unsplash-retry">Click here to return to browsing.</span></div>');
            }
          })
          .error(function (type) {
            $(".unsplash-browser").html('<div class="mtnc-loader">An error occured downloading the image.<br /><span class="mtnc-unsplash-retry">Click here to return to browsing.</span></div>');
          })
          .always(function (type) {
            $(".mtnc-media-button-select").removeAttr("disabled");
          });
      }
    }
  });

  function getUploader($text, $target) {
    if (custom_uploader) {
      custom_uploader.detach();
    }

    // Extend the wp.media object
    custom_uploader = wp.media.frames.file_frame = wp.media({
      title: $text,
      button: {
        text: $text,
      },
      multiple: false,
    });

    // When a file is selected, grab the URL and set it as the text field's value
    custom_uploader.on("select", function () {
      var attachment = custom_uploader.state().get("selection").first().toJSON();
      $target.parents(".mtnc-image-upload-preview-wrapper").parent().find("input").val(attachment.url);
      $target.parents(".mtnc-image-upload-preview-wrapper").find("img").remove();
      $target.parent().css("background-image", "url(" + attachment.url + ")");
      $target.parent().append('<button type="button" class="button mtnc-image-upload-remove" style="margin-top: 4px">Remove</button>');
    });

    // Open the uploader dialog
    custom_uploader.open();
  }

   //font preview
  $(document).on("change", ".mtnc-bunny-fonts", function () {
    var $font = $(this);
    changeFont($font);
  });

  function changeFont($font) {
    var $fontValue = $font.val();

    reloadFont($fontValue);
    $font.parent().find("h3").css("font-family", $fontValue);
  }

  function reloadFont($fontValue) {
    baseFonts = ["Arial", "Helvetica", "Georgia", "Times New Roman", "Tahoma", "Verdana", "Geneva"];
    if (baseFonts.indexOf($fontValue) != -1) {
      return;
    }

    WebFont.load({
      google: {
        families: [$fontValue],
      },
    });
  }

  // Get random ID
  function get_random_id() {
    return (
      Math.floor((1 + Math.random()) * 0x10000)
        .toString(16)
        .substring(1) +
      Math.floor((1 + Math.random()) * 0x10000)
        .toString(16)
        .substring(1)
    );
  } // get_random_id

  $("#maintenance_password_toggle")
    .on("change", function (e) {
      if ($(this).is(":checked")) {
        $("#maintenance_password_wrapper").show();
      } else {
        $("#maintenance_password_wrapper").hide();
        $("#maintenance_password").val("");
      }
    })
    .trigger("change");



  $("#background_image_filter")
    .on("change", function (e) {
      filter = $(this).val();
      image = $(".mtnc-image-upload-preview-wrapper");
      if (!image.length) {
        return;
      }

      $(image).removeClass();
      $(image).addClass("mtnc-image-upload-preview-wrapper");
      $(image).addClass(filter);
    })
    .trigger("change");

  $("#custom_wp_maintenance")
    .on("change", function (e) {
      if ($(this).is(":checked")) {
        $(".custom_wp_maintenance_options").show();
      } else {
        $(".custom_wp_maintenance_options").hide();
        $("#custom_wp_maintenance_title").val("");
        $("#custom_wp_maintenance_content").val("");
      }
    })
    .trigger("change");

  //Save settings
  function save_ajax(theme_new, theme_name) {
    let error = false;
    let error_message = "";

    if (typeof tinymce != "undefined" && tinymce && tinymce.get("custom_wp_maintenance_content")) {
      $("#custom_wp_maintenance_content").val(tinymce.get("custom_wp_maintenance_content").getContent());
    }

    let data = $("form.mtnc-body *").not(".skip-save").serialize();
    $("#mtnc_submit").addClass("loading").find("strong").html("Please wait ...");

    if (error !== false) {
      mtnc_show_swal(error_message, "error", true, false, 2500);
      return false;
    }

    if (!theme_name) {
      theme_name = theme.name;
    }

    block = block_ui("Saving");
    $.post({
      url: ajaxurl,
      data: {
        action: "mtnc_action",
        _ajax_nonce: mtnc.nonce_action,
        mtnc_action: "save",
        theme_global: mtnc.theme_global,
        theme_id: mtnc.theme_id,
        theme: JSON.stringify(theme),
        theme_new: theme_new,
        theme_name: theme_name,
        extra_data: data,
      },
    })
      .always(function (data) {
        mtnc_swal.close();
      })
      .done(function (data) {
        if (data.success) {
          mtnc.theme_id = data.data.theme_id;
          $(".mtnc-theme-title-name").html(data.data.theme.name);
          theme = data.data.theme;

          var url = new URL(location.href);
          var search_params = url.searchParams;
          search_params.set("theme_id", data.data.theme_id);
          url.search = search_params.toString();
          var new_url = url.toString();
          mtnc_swal.fire({ type: "success", title: 'Settings Saved', showConfirmButton: false, timer: 1000});
          window.history.replaceState("maintenance", "Maintenance", new_url);
        }
      })
      .fail(function (data) {
        mtnc_swal.fire({ type: "error", title: mtnc.undocumented_error });
      });
  } // save settings

  const url = new URL(window.location.href);
  if (url.searchParams.get('settings-reset') === 'true') {
    url.searchParams.delete('settings-reset');
    window.history.replaceState({}, document.title, url.toString());
    mtnc_swal.fire({ type: "success", title: 'Settings have been reset', showConfirmButton: false, timer: 1000});
  }

  if (url.searchParams.get('theme-reset') === 'true') {
    url.searchParams.delete('theme-reset');
    window.history.replaceState({}, document.title, url.toString());
    mtnc_swal.fire({ type: "success", title: 'Theme has been reset', showConfirmButton: false, timer: 1000});
  }

  $(".mtnc-export-theme").on("click", function (e) {
    e.preventDefault();
    var export_theme_id = $(this).data("theme");
    block = block_ui("Exporting Theme");
    $.post({
      url: ajaxurl,
      data: {
        action: "mtnc_action",
        _ajax_nonce: mtnc.nonce_action,
        mtnc_action: "export_theme",
        theme_id: export_theme_id,
      },
    })
      .always(function (data) {
        mtnc_swal.close();
      })
      .done(function (data) {
        if (data.success) {
          window.location.assign(data.data.template_path);
        }
      })
      .fail(function (data) {
        mtnc_swal.fire({ type: "error", title: mtnc.undocumented_error });
      });
  });

  // display a message while an action is performed
  function mtnc_show_swal(message, type, confirm, cancel, close) {
    tmp = mtnc_swal.fire({
      title: message,
      icon: type,
      imageWidth: 100,
      imageHeight: 100,
      imageAlt: message,
      timer: close,
      timerProgressBar: true,
      allowOutsideClick: false,
      allowEscapeKey: false,
      allowEnterKey: false,
      showConfirmButton: confirm,
      showCancelButton: cancel,
      customClass: {
        container: "mtnc-swal-message",
      },
    });

    return tmp;
  } // block_ui

  // display a message while an action is performed
  function block_ui(message) {
    tmp = mtnc_swal.fire({
      text: message,
      type: false,
      imageUrl: mtnc.icon_url,
      onOpen: () => {
        $(mtnc_swal.getImage()).addClass("mtnc_rotating");
      },
      imageWidth: 100,
      imageHeight: 100,
      imageAlt: message,
      allowOutsideClick: false,
      allowEscapeKey: false,
      allowEnterKey: false,
      showConfirmButton: false,
    });

    return tmp;
  } // block_ui

  $("body").on("click", ".mtnc-confirm", function (e) {
    e.preventDefault();
    $href = $(this).attr("href");
    mtnc_swal
      .fire({
        title: $(this).data("confirm-title"),
        html: $(this).data("confirm-text"),
        showCancelButton: true,
        focusConfirm: false,
        confirmButtonText: $(this).data("confirm-button"),
        focusCancel: true,
        width: 600,
      })
      .then((result) => {
        if (result.dismiss || typeof result.value == "undefined") {
          return;
        } else {
          window.location.href = $href;
        }
      });
  });

  jQuery('.install-wpfssl').on('click',function(e){
    if (!confirm('The free WP Force SSL plugin will be installed & activated from the official WordPress repository.')) {
      return;
    }

    jQuery('body').append('<div style="width:550px;height:450px; position:fixed;top:10%;left:50%;margin-left:-275px; color:#444; background-color: #fbfbfb;border:1px solid #DDD; border-radius:4px;box-shadow: 0px 0px 0px 4000px rgba(0, 0, 0, 0.85);z-index: 9999999;"><iframe src="' + mtnc.wpfssl_install_url + '" style="width:100%;height:100%;border:none;" /></div>');
    jQuery('#wpwrap').css('pointer-events', 'none');
    e.preventDefault();
    return false;
  });

  jQuery('.install-wpcaptcha').on('click',function(e){
    if (!confirm('The free Advanced Google reCAPTCHA plugin will be installed & activated from the official WordPress repository.')) {
      return;
    }

    jQuery('body').append('<div style="width:550px;height:450px; position:fixed;top:10%;left:50%;margin-left:-275px; color:#444; background-color: #fbfbfb;border:1px solid #DDD; border-radius:4px;box-shadow: 0px 0px 0px 4000px rgba(0, 0, 0, 0.85);z-index: 9999999;"><iframe src="' + mtnc.wpcaptcha_install_url + '" style="width:100%;height:100%;border:none;" /></div>');
    jQuery('#wpwrap').css('pointer-events', 'none');
    e.preventDefault();
    return false;
  });


  $('.toplevel_page_maintenance').on('click', '.open-weglot-upsell', function(e) {
    e.preventDefault();

    $(this).blur();

    $('#weglot-upsell-dialog').dialog('open');

    return false;
  });

  $('#weglot').on('click change', function(e) {
    e.preventDefault();
    $(this).prop("checked", false);

    $('.open-weglot-upsell').first().trigger('click');

    return false;
  });

  $('#weglot-upsell-dialog').dialog({'dialogClass': 'wp-dialog mtnc-dialog weglot-upsell-dialog',
                              'modal': 1,
                              'resizable': false,
                              'title': 'Translate your maintenance page to any language',
                              'zIndex': 9999,
                              'width': 550,
                              'height': 'auto',
                              'show': 'fade',
                              'hide': 'fade',
                              'open': function(event, ui) {
                                maintenance_fix_dialog_close(event, ui);
                                $(this).siblings().find('span.ui-dialog-title').html(mtnc.weglot_dialog_upsell_title);
                              },
                              'close': function(event, ui) { },
                              'autoOpen': false,
                              'closeOnEscape': true
  });
  $(window).resize(function(e) {
    $('#weglot-upsell-dialog').dialog("option", "position", {my: "center", at: "center", of: window});
  });


  jQuery('#install-weglot').on('click',function(e){
    $('#weglot-upsell-dialog').dialog('close');
    jQuery('body').append('<div style="width:550px;height:450px; position:fixed;top:10%;left:50%;margin-left:-275px; color:#444; background-color: #fbfbfb;border:1px solid #DDD; border-radius:4px;box-shadow: 0px 0px 0px 4000px rgba(0, 0, 0, 0.85);z-index: 9999999;"><iframe src="' + mtnc.weglot_install_url + '" style="width:100%;height:100%;border:none;" /></div>');
    jQuery('#wpwrap').css('pointer-events', 'none');
    e.preventDefault();
    return false;
  });




  $("#per_url_settings")
    .on("change", function (e) {
      if ($(this).val() == "") {
        $(".per-url-wrapper").hide();
      } else {
        $(".per-url-wrapper").show();
      }
    })
    .trigger("change");


  $(".mtnc-alert .notice-dismiss").on("click", function (e) {
    e.preventDefault();

    $(this).parents(".mtnc-alert").fadeOut();

    return false;
  });

  if (localStorage.getItem('maintenance-promo-review-hide')) {
    jQuery('#promo-review2').hide();
  } else {
    jQuery('#promo-review2').show();
  }

  jQuery('.hide-review-box2').on('click', function(e) {
    e.preventDefault();
    jQuery('#promo-review2').hide();
    localStorage.setItem('maintenance-promo-review-hide', true);

    return false;
  });

$('#wpwrap').on('click', '.open-pro-dialog', function (e) {
    e.preventDefault();
    $(this).blur();

    pro_feature = $(this).data('pro-feature');
    if (!pro_feature) {
      pro_feature = $(this).parent('label').attr('for');
    }
    if (!pro_feature) {
      pro_feature = $(this).parents('.mtnc-form-group').children('label').data('pro-feature');
    }
    if (!pro_feature) {
      pro_feature = $(this).siblings('label').data('pro-feature');
    }

    open_upsell(pro_feature);

    return false;
  });



  function clean_feature(feature) {
    feature = feature || 'free-v2-unknown';
    feature = feature.toLowerCase();
    feature = feature.replace(' ', '-');

    return 'free-v2-' + feature;
  }


  if (window.localStorage.getItem("mtnc_upsell_shown_timestamp") === null || new Date().getTime() / 1000 - window.localStorage.getItem("mtnc_upsell_shown_timestamp") > 86400 * 90) {
    window.localStorage.setItem("mtnc_upsell_shown_timestamp", Math.round(new Date().getTime() / 1000));
    window.localStorage.setItem('mtnc_upsell_shown', 'true');

    open_upsell('welcome');
  }


});

function maintenance_fix_dialog_close(event, ui) {
  jQuery('.ui-widget-overlay').bind('click', function(){
    jQuery('#' + event.target.id).dialog('close');
  });
} // maintenance_fix_dialog_close
