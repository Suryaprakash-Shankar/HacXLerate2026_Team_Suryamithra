/**
 * SURYAMITHRA Firebase Authentication helper (Google + Microsoft)
 *
 * Flow: signInWithPopup (called synchronously inside the click so browsers
 * don't block it) -> if popup is blocked, fall back to signInWithRedirect ->
 * send the Firebase ID TOKEN to api/firebase_auth.php, which verifies it.
 */
window.SURYAMITHRA_FIREBASE = window.SURYAMITHRA_FIREBASE || { initialized: false, auth: null, busy: false };

var SURYA_PENDING_KEY = 'surya_auth_pending';

function suryaEndpoint(options) {
  return (options && options.endpoint) ||
    ((window.BASE_URL ? window.BASE_URL : '') + '/api/firebase_auth.php');
}

function initSuryaFirebase(config) {
  if (typeof firebase === 'undefined') {
    console.warn('Firebase JS SDK not loaded');
    return false;
  }
  if (window.SURYAMITHRA_FIREBASE.initialized) return true;
  config = config || {};

  if (!firebase.apps.length) {
    firebase.initializeApp({
      apiKey: config.apiKey,
      authDomain: config.authDomain,
      projectId: config.projectId,
      storageBucket: config.storageBucket,
      messagingSenderId: config.messagingSenderId,
      appId: config.appId
    });
  }

  window.SURYAMITHRA_FIREBASE.auth = firebase.auth();
  window.SURYAMITHRA_FIREBASE.initialized = true;

  // Finish a redirect-based sign-in (used only when the popup was blocked)
  var pending = null;
  try { pending = JSON.parse(sessionStorage.getItem(SURYA_PENDING_KEY) || 'null'); } catch (e) {}
  if (pending) {
    window.SURYAMITHRA_FIREBASE.auth.getRedirectResult().then(function (result) {
      sessionStorage.removeItem(SURYA_PENDING_KEY);
      if (result && result.user) {
        return result.user.getIdToken().then(function (idToken) {
          suryaSendToBackend(idToken, pending.provider, pending.options, null);
        });
      }
    }).catch(function (err) {
      sessionStorage.removeItem(SURYA_PENDING_KEY);
      suryaShowError(err);
    });
  }
  return true;
}

function suryaBuildProvider(name) {
  if (name === 'google') {
    var g = new firebase.auth.GoogleAuthProvider();
    g.addScope('email');
    g.addScope('profile');
    g.setCustomParameters({ prompt: 'select_account' });
    return g;
  }
  if (name === 'microsoft') {
    var m = new firebase.auth.OAuthProvider('microsoft.com');
    m.addScope('email');
    m.addScope('profile');
    m.setCustomParameters({ prompt: 'select_account', tenant: 'common' });
    return m;
  }
  return null;
}

function suryaShowError(err) {
  var code = (err && err.code) || '';
  var msg;
  switch (code) {
    case 'auth/popup-closed-by-user':
    case 'auth/cancelled-popup-request':
      return; // user closed it themselves, stay quiet
    case 'auth/unauthorized-domain':
      msg = 'This website (' + location.hostname + ') is not authorized in Firebase.\n' +
            'Firebase Console > Authentication > Settings > Authorized domains > add: ' + location.hostname;
      break;
    case 'auth/operation-not-allowed':
      msg = 'This sign-in provider is not enabled.\nFirebase Console > Authentication > Sign-in method > enable it.';
      break;
    case 'auth/network-request-failed':
      msg = 'Network error. Check your internet connection and try again.';
      break;
    case 'auth/account-exists-with-different-credential':
      msg = 'An account already exists with this email using a different sign-in method.';
      break;
    default:
      msg = (err && err.message) || 'Authentication failed. Please try again.';
  }
  console.error('Firebase auth error:', err);
  alert(msg);
}

function suryaSendToBackend(idToken, providerName, options, restore) {
  var fd = new FormData();
  fd.append('id_token', idToken);
  fd.append('provider', providerName);
  fd.append('csrf', options.csrf || window.SURYA_CSRF || '');
  fd.append('mode', options.mode || 'login');
  fd.append('name', options.name || '');
  fd.append('role', options.role || 'student');
  fd.append('department', options.department || 'CSE');
  fd.append('class_name', options.class_name || 'Class A');
  fd.append('semester', options.semester || 5);
  fd.append('role_id', options.role_id || 1);
  fd.append('student_code', options.student_code || '');

  fetch(suryaEndpoint(options), { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(function (res) { return res.text(); })
    .then(function (text) {
      var data;
      try { data = JSON.parse(text); }
      catch (e) { throw new Error('Server returned an invalid response. (' + text.slice(0, 120) + ')'); }
      if (data.redirect) {
        if (data.status !== 'success' && data.message) alert(data.message);
        window.location.href = data.redirect;
      } else {
        alert(data.message || 'Authentication failed.');
        window.SURYAMITHRA_FIREBASE.busy = false;
        if (restore) restore();
      }
    })
    .catch(function (err) {
      console.error('Auth API error:', err);
      alert(err.message || 'Authentication error. Please try again.');
      window.SURYAMITHRA_FIREBASE.busy = false;
      if (restore) restore();
    });
}

function suryaFirebaseAuth(providerName, options, evt) {
  options = options || {};
  var state = window.SURYAMITHRA_FIREBASE;
  if (state.busy) return;

  if (!state.initialized && typeof firebase !== 'undefined' && window.SURYA_FB_CONFIG) {
    initSuryaFirebase(window.SURYA_FB_CONFIG);
  }
  if (!state.initialized) {
    alert('Firebase failed to load. Check your internet connection and reload the page.');
    return;
  }

  var provider = suryaBuildProvider(providerName);
  if (!provider) { alert('Unknown sign-in provider.'); return; }

  var btn = evt ? (evt.currentTarget || evt.target) : null;
  while (btn && btn.tagName !== 'BUTTON') btn = btn.parentElement;
  var origHtml = btn ? btn.innerHTML : '';
  function restore() { if (btn) { btn.innerHTML = origHtml; btn.disabled = false; } state.busy = false; }

  state.busy = true;
  var startedAt = Date.now();
  if (btn) { btn.disabled = true; btn.innerHTML = 'Authenticating with ' + (providerName === 'google' ? 'Google' : 'Microsoft') + '...'; }

  // IMPORTANT: no await / setTimeout before this call, or the browser blocks the popup.
  state.auth.signInWithPopup(provider).then(function (result) {
    return result.user.getIdToken().then(function (idToken) {
      suryaSendToBackend(idToken, providerName, options, restore);
    });
  }).catch(function (err) {
    if (err && err.code === 'auth/popup-blocked') {
      // Popup blocked by the browser -> use full-page redirect instead
      try { sessionStorage.setItem(SURYA_PENDING_KEY, JSON.stringify({ provider: providerName, options: options })); } catch (e) {}
      state.auth.signInWithRedirect(provider);
      return;
    }
    restore();
    var fast = (Date.now() - startedAt) < 3000;
    if (err && err.code === 'auth/popup-closed-by-user' && fast) {
      // Popup died instantly: this is a Firebase/Google setup problem, not the user closing it.
      if (confirm('The Google sign-in window closed immediately.\n\n' +
          'Most likely cause: "' + location.hostname + '" is missing from Firebase Console > Authentication > Settings > Authorized domains, ' +
          'or the Google provider is not enabled.\n\nClick OK to try full-page sign-in instead (it will show the exact error from Firebase).')) {
        try { sessionStorage.setItem(SURYA_PENDING_KEY, JSON.stringify({ provider: providerName, options: options })); } catch (e) {}
        state.auth.signInWithRedirect(provider);
      }
      return;
    }
    suryaShowError(err);
  });
}
