// i18n Language Switcher
(function() {
  var LANG_KEY = 'bloodlife-lang';

  function getLang() {
    return localStorage.getItem(LANG_KEY) || 'en';
  }

  function t(key) {
    var lang = getLang();
    return (TRANSLATIONS[lang] && TRANSLATIONS[lang][key]) || (TRANSLATIONS.en && TRANSLATIONS.en[key]) || key;
  }

  function applyLang() {
    var lang = getLang();
    document.documentElement.lang = lang === 'my' ? 'my' : 'en';

    // Update all elements with data-i18n attribute
    document.querySelectorAll('[data-i18n]').forEach(function(el) {
      var key = el.getAttribute('data-i18n');
      var translated = t(key);
      if (translated) {
        el.textContent = translated;
      }
    });

    // Update placeholders
    document.querySelectorAll('[data-i18n-placeholder]').forEach(function(el) {
      var key = el.getAttribute('data-i18n-placeholder');
      var translated = t(key);
      if (translated) {
        el.placeholder = translated;
      }
    });

    // Update select dropdowns for theme
    document.querySelectorAll('.theme-toggle-select').forEach(function(sel) {
      var optLight = sel.querySelector('option[value="light"]');
      var optDark = sel.querySelector('option[value="dark"]');
      if (optLight) optLight.textContent = t('light');
      if (optDark) optDark.textContent = t('dark');
    });

    // Sync language select dropdowns
    document.querySelectorAll('.lang-toggle-select').forEach(function(sel) {
      sel.value = lang;
    });
  }

  // Expose for external use
  window.bloodlifeI18n = { t: t, applyLang: applyLang, getLang: getLang };

  // Apply on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      applyLang();
      bindEvents();
    });
  } else {
    applyLang();
    bindEvents();
  }

  function bindEvents() {
    document.querySelectorAll('.lang-toggle-select').forEach(function(sel) {
      sel.value = getLang();
      sel.addEventListener('change', function() {
        localStorage.setItem(LANG_KEY, this.value);
        applyLang();
      });
    });
  }
})();
