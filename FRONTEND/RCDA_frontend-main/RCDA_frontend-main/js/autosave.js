/* ============================================================
   autosave.js — loaded on EVERY page, after config.js.

   Generic "save the user's progress as they type" helper.
   Never stores password fields. Respects RCDA_CONFIG.FEATURES.autosaveForms.

   Usage:
     RCDA.autosave.attach('signupForm', ['regFirst','regLast','regEmail','regPhone','regCourse']);
     // later, after a successful submit:
     RCDA.autosave.clear('signupForm');
   ============================================================ */

window.RCDA = window.RCDA || {};

(function () {

  const PREFIX = 'rcda_draft_';
  const SENSITIVE_HINTS = ['pass', 'cvv', 'card', 'pin']; // never persist these

  function isEnabled(){
    return !window.RCDA_CONFIG || RCDA_CONFIG.FEATURES.autosaveForms !== false;
  }

  function isSensitive(fieldId){
    const id = (fieldId || '').toLowerCase();
    return SENSITIVE_HINTS.some(hint => id.includes(hint));
  }

  function debounce(fn, wait){
    let t;
    return function (...args){
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait);
    };
  }

  const autosave = {

    /**
     * Attach autosave to a set of field ids under a named draft key.
     * @param {string} draftKey - unique name for this form's draft, e.g. 'signupForm'
     * @param {string[]} fieldIds - element ids to watch/restore (passwords are skipped automatically)
     * @param {object} [opts]
     * @param {function} [opts.onRestore] - called with the restored values object, if a draft existed
     */
    attach(draftKey, fieldIds, opts) {
      if (!isEnabled()) return;
      opts = opts || {};
      const storageKey = PREFIX + draftKey;
      const safeFieldIds = fieldIds.filter(id => !isSensitive(id));

      // Restore
      try {
        const raw = localStorage.getItem(storageKey);
        if (raw) {
          const saved = JSON.parse(raw);
          safeFieldIds.forEach(id => {
            const el = document.getElementById(id);
            if (el && saved[id] !== undefined) el.value = saved[id];
          });
          if (typeof opts.onRestore === 'function') opts.onRestore(saved);
        }
      } catch (err) {
        console.warn('[RCDA] autosave restore failed:', err);
      }

      // Save on input (debounced so we're not hitting localStorage on every keystroke)
      const save = debounce(() => {
        try {
          const values = {};
          safeFieldIds.forEach(id => {
            const el = document.getElementById(id);
            if (el) values[id] = el.value;
          });
          localStorage.setItem(storageKey, JSON.stringify(values));
        } catch (err) {
          // Storage can fail (quota, private mode) — fail silently, autosave is a nicety
          console.warn('[RCDA] autosave save failed:', err);
        }
      }, 400);

      safeFieldIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', save);
      });
    },

    /** Clear a draft — call this after a successful submit. */
    clear(draftKey) {
      try { localStorage.removeItem(PREFIX + draftKey); }
      catch (err) { /* ignore */ }
    },

    /** Whether a draft currently exists (useful for showing a "Draft restored" hint). */
    has(draftKey) {
      try { return !!localStorage.getItem(PREFIX + draftKey); }
      catch (err) { return false; }
    }
  };

  RCDA.autosave = autosave;

})();