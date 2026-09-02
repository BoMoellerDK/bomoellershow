(function () {
  'use strict';

  document.querySelectorAll('[data-copy-link]').forEach(function (button) {
    button.addEventListener('click', function () {
      var link = button.getAttribute('data-copy-link') || '';
      var original = button.textContent;
      var done = function () {
        button.textContent = 'Kopieret ✓';
        window.setTimeout(function () { button.textContent = original; }, 1500);
      };
      var fallback = function () {
        var field = document.createElement('textarea');
        field.value = link;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.left = '-9999px';
        document.body.appendChild(field);
        field.select();
        var copied = false;
        try { copied = document.execCommand('copy'); } catch (error) {}
        document.body.removeChild(field);
        if (copied) done();
      };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(link).then(done, fallback);
      } else {
        fallback();
      }
    });
  });
}());
