try {
  new MutationObserver(function() {
  });
  new Promise(function() {
  });
  Object.assign({}, {});
  document.fonts.ready.then(function() {
  });
} catch (e) {
  var replacement = false ? "fpodisable=1" : "wpmeteordisable=1";
  var href = document.location.href;
  if (!(false ? href.match(/[?&]fpodisable/) : href.match(/[?&]wpmeteordisable/))) {
    var nhref = "";
    if (href.indexOf("?") == -1) {
      if (href.indexOf("#") == -1) {
        nhref = href + "?" + replacement;
      } else {
        nhref = href.replace("#", "?" + replacement + "#");
      }
    } else {
      if (href.indexOf("#") == -1) {
        nhref = href + "&" + replacement;
      } else {
        nhref = href.replace("#", "&" + replacement + "#");
      }
    }
    document.location.href = nhref;
  }
}
//# sourceMappingURL=ie-redirect.js.map
