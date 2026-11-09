import axios from 'axios';
import { reactive } from 'vue';

// Client HTTP : cookies Sanctum (SPA) ou jeton Bearer si l'API en renvoie un.
export const http = axios.create({ baseURL: '/api', withCredentials: true, withXSRFToken: true, headers: { Accept: 'application/json' } });
http.interceptors.request.use((c) => {
    const t = localStorage.getItem('jeton');
    if (t) c.headers.Authorization = 'Bearer ' + t;
    return c;
});

export const auth = reactive({ user: null, ready: false, bienvenue: false });
export const toasts = reactive([]);
export function notifier(message, type = 'ok') {
    const t = { message, type, id: Math.random() };
    toasts.push(t);
    setTimeout(() => toasts.splice(toasts.indexOf(t), 1), 4500);
}
// Boîte de confirmation partagée (voir App.vue).
export const confirmation = reactive({ open: false, texte: '', danger: false, ok: null });
export const confirmer = (texte) => new Promise((res) => {
    Object.assign(confirmation, { open: true, texte, danger: /^(supprimer|retirer)/i.test(texte), ok: (v) => { confirmation.open = false; res(v); } });
});

export const unwrap = (r) => r.data?.data ?? r.data;
export const liste = (x) => (Array.isArray(x) ? x : x?.data ?? []);
export const erreur = (e) => {
    const d = e.response?.data;
    if (d?.errors) return Object.values(d.errors).flat().join(' ');
    return d?.message || 'Une erreur est survenue. Réessayez.';
};
export const estAdmin = () => /admin/i.test(auth.user?.role?.nom ?? auth.user?.role ?? '');
export const roleLabel = () => (estAdmin() ? 'Administrateur' : 'Enseignant');
export const nomUser = (u = auth.user) => u?.nom ?? u?.name ?? u?.email ?? '';

// Libellé d'un objet, quel que soit le nom du champ renvoyé par l'API.
export const lib = (o) => o?.intitule ?? o?.nom ?? o?.libelle ?? o?.titre ?? o?.name ?? o?.code ?? '';
const ETIQ = { code: 'Code EC', credits: 'Crédits EC', credit: 'Crédits EC', et: 'ET', ed: 'ED', ep: 'EP', vh: 'VH', tpe: 'TPE', volume_horaire_total: 'VH', vht: 'VH', volume_horaire: 'VH', intitule: 'Éléments constitutifs (EC)' };
export const etiq = (k) => ETIQ[k.toLowerCase()] ?? k.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());
export const idDe = (o) => o?.id ?? o?.public_id ?? o?.ulid;
export const statutDe = (o) => {
    const s = String(o?.statut ?? o?.status ?? '').toLowerCase();
    return s.startsWith('publi') ? 'publie' : s.startsWith('archiv') ? 'archive' : 'brouillon';
};
export const STATUT = { brouillon: 'Brouillon', publie: 'Publié', archive: 'Archivé' };
// Champs simples (texte, nombre) d'un objet, hors identifiants et dates techniques.
export const scalaires = (o) => Object.entries(o ?? {}).filter(([k, v]) =>
    v !== null && typeof v !== 'object' && !/^(id|ulid|public_id|.*_id|.*_at)$/.test(k));

export async function charger() {
    try { auth.user = unwrap(await http.get('/user')); } catch { auth.user = null; localStorage.removeItem('jeton'); }
    auth.ready = true;
}
export async function connecter(email, password) {
    try { await axios.get('/sanctum/csrf-cookie', { withCredentials: true }); } catch { /* mode jeton */ }
    const r = await http.post('/login', { email, password });
    const d = unwrap(r);
    const t = d?.token ?? r.data?.token;
    if (t) localStorage.setItem('jeton', t);
    auth.user = d?.user ?? unwrap(await http.get('/user'));
}
export async function deconnecter() {
    try { await http.post('/logout'); } catch { /* session déjà expirée */ }
    localStorage.removeItem('jeton');
    auth.user = null;
}

// Télécharge un fichier (PDF) généré par le serveur, avec la session ou le jeton de l'utilisateur.
export async function telecharger(chemin) {
    const r = await http.get(chemin, { responseType: 'blob' });
    const nom = /filename="?([^";]+)"?/i.exec(r.headers['content-disposition'] ?? '')?.[1] ?? 'document.pdf';
    const url = URL.createObjectURL(r.data);
    const a = document.createElement('a');
    a.href = url; a.download = nom; document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
}

// Taille du texte réglable (A- / A+), mémorisée dans le navigateur.
export const prefs = reactive({ taille: 15 });
try { const t = Number(localStorage.getItem('taille')); if (t >= 13 && t <= 19) prefs.taille = t; } catch { /* stockage indisponible */ }
export const appliquerTaille = () => { document.documentElement.style.fontSize = prefs.taille + 'px'; };
export function changerTaille(delta) {
    prefs.taille = Math.min(19, Math.max(13, prefs.taille + delta));
    try { localStorage.setItem('taille', String(prefs.taille)); } catch { /* stockage indisponible */ }
    appliquerTaille();
}
appliquerTaille();
