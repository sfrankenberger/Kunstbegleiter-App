/*
 * Kunstbegleiter: Passkeys (WebAuthn) im Browser. Ruft die Routen des Pakets laravel/passkeys:
 * GET /passkeys/login/options, POST /passkeys/login, GET /user/passkeys/options, POST /user/passkeys.
 * ES-Modul, auch als window.KunstPasskeys fuer Alpine. Vorlage: Tourtool public/js/passkeys.js.
 */

function bufToB64url(buffer) {
  const bytes = new Uint8Array(buffer);
  let binary = '';
  for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function b64urlToBuf(value) {
  const base64 = String(value).replace(/-/g, '+').replace(/_/g, '/');
  const padded = base64 + '==='.slice(0, (4 - (base64.length % 4)) % 4);
  const binary = atob(padded);
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
  return bytes.buffer;
}

function csrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute('content') || '' : '';
}

async function call(method, path, body) {
  const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  let res;
  try {
    res = await fetch(path, { method, headers, body: body !== undefined ? JSON.stringify(body) : undefined, credentials: 'same-origin', cache: 'no-store' });
  } catch (e) {
    throw new Error('Keine Verbindung. Bitte das Internet prüfen.');
  }
  let data = null;
  try { data = await res.json(); } catch (e) { data = null; }
  if (!res.ok) {
    const err = new Error((data && data.message) || ('Fehler ' + res.status));
    err.status = res.status;
    throw err;
  }
  return data;
}

export function passkeySupported() {
  return !!(window.PublicKeyCredential && navigator.credentials && navigator.credentials.create && window.isSecureContext);
}

function toCreationOptions(options) {
  if (window.PublicKeyCredential && PublicKeyCredential.parseCreationOptionsFromJSON) {
    return PublicKeyCredential.parseCreationOptionsFromJSON(options);
  }
  const out = { ...options, challenge: b64urlToBuf(options.challenge), user: { ...options.user, id: b64urlToBuf(options.user.id) } };
  if (Array.isArray(options.excludeCredentials)) out.excludeCredentials = options.excludeCredentials.map(c => ({ ...c, id: b64urlToBuf(c.id) }));
  return out;
}

function toRequestOptions(options) {
  if (window.PublicKeyCredential && PublicKeyCredential.parseRequestOptionsFromJSON) {
    return PublicKeyCredential.parseRequestOptionsFromJSON(options);
  }
  const out = { ...options, challenge: b64urlToBuf(options.challenge) };
  if (Array.isArray(options.allowCredentials)) out.allowCredentials = options.allowCredentials.map(c => ({ ...c, id: b64urlToBuf(c.id) }));
  return out;
}

function credentialToJson(credential) {
  if (typeof credential.toJSON === 'function') return credential.toJSON();
  const r = credential.response;
  const response = { clientDataJSON: bufToB64url(r.clientDataJSON) };
  if (r.attestationObject) {
    response.attestationObject = bufToB64url(r.attestationObject);
    if (typeof r.getTransports === 'function') response.transports = r.getTransports();
  } else {
    response.authenticatorData = bufToB64url(r.authenticatorData);
    response.signature = bufToB64url(r.signature);
    response.userHandle = r.userHandle ? bufToB64url(r.userHandle) : null;
  }
  return {
    id: credential.id,
    rawId: bufToB64url(credential.rawId),
    type: credential.type,
    authenticatorAttachment: credential.authenticatorAttachment || undefined,
    clientExtensionResults: typeof credential.getClientExtensionResults === 'function' ? credential.getClientExtensionResults() : {},
    response,
  };
}

/** Neuen Passkey fuer die angemeldete Person anlegen. */
export async function registerPasskey(name) {
  const { options } = await call('GET', '/user/passkeys/options');
  const credential = await navigator.credentials.create({ publicKey: toCreationOptions(options) });
  if (!credential) throw new Error('Es wurde kein Passkey angelegt.');
  return call('POST', '/user/passkeys', { name, credential: credentialToJson(credential) });
}

/** Mit Passkey anmelden (ohne E-Mail). Liefert { redirect }. */
export async function loginWithPasskey() {
  const { options } = await call('GET', '/passkeys/login/options');
  const credential = await navigator.credentials.get({ publicKey: toRequestOptions(options) });
  if (!credential) throw new Error('Es wurde kein Passkey gewählt.');
  return call('POST', '/passkeys/login', { credential: credentialToJson(credential), remember: true });
}

/** Lesbare Meldung fuer einen fehlgeschlagenen Passkey-Vorgang. */
export function passkeyErrorMessage(error) {
  const name = error && error.name;
  if (name === 'NotAllowedError' || name === 'AbortError') return 'Abgebrochen oder keine Bestätigung. Bitte noch einmal versuchen.';
  if (name === 'InvalidStateError') return 'Auf diesem Gerät gibt es schon einen Passkey für dieses Konto.';
  if (name === 'SecurityError') return 'Passkeys funktionieren nur über HTTPS auf der richtigen Domain.';
  return (error && error.message) || 'Das hat nicht geklappt.';
}

window.KunstPasskeys = { passkeySupported, registerPasskey, loginWithPasskey, passkeyErrorMessage };
