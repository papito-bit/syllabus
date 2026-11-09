<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import Historique from './Historique.vue';
import { http, auth, unwrap, liste, erreur, notifier, estAdmin, nomUser, confirmer } from '../api';
// Noms de champs envoyés à l'API : à ajuster ici si ton backend en utilise d'autres.
const NK = 'name';
const onglet = ref('compte'); const voirMdp = ref(false); const voirNouveau = ref(false);
const profil = reactive({ [NK]: nomUser(), email: auth.user?.email ?? '' });
const mdp = reactive({ current_password: '', password: '', password_confirmation: '' });
const users = ref([]); const roleAdmin = computed(() => users.value.find((u) => /admin/i.test(u.role))?.role ?? 'admin');
const ROLES = computed(() => ({ [roleAdmin.value]: 'Administrateur', enseignant: 'Enseignant' }));
const nouveau = reactive({ [NK]: '', email: '', role: 'enseignant', password: '' }); const temp = ref('');

async function agir(f, ok) { try { await f(); notifier(ok); } catch (e) { notifier(erreur(e), 'error'); } }
const majProfil = () => agir(async () => { auth.user = { ...auth.user, ...unwrap(await http.put('/profil', profil)) }; }, 'Profil mis à jour.');
const majMdp = () => agir(async () => { await http.put('/profil/mot-de-passe', mdp); Object.keys(mdp).forEach((k) => (mdp[k] = '')); }, 'Mot de passe modifié.');
const charger = () => agir(async () => { users.value = liste(unwrap(await http.get('/admin/utilisateurs'))); }, 'Liste actualisée.').then(() => {});
const creer = () => agir(async () => {
    const corps = { ...nouveau }; if (!corps.password) delete corps.password;
    const r = unwrap(await http.post('/admin/utilisateurs', corps));
    temp.value = Object.entries(r ?? {}).find(([k]) => /mot_de_passe|password/.test(k) && typeof r[k] === 'string')?.[1] ?? '';
    Object.assign(nouveau, { [NK]: '', email: '', password: '' });
    users.value = liste(unwrap(await http.get('/admin/utilisateurs')));
}, 'Compte créé.');
async function basculer(u) {
    const actif = u.is_active;
    if (!(await confirmer(`${actif ? 'Désactiver' : 'Réactiver'} le compte de ${u[NK] ?? u.email} ?`))) return;
    agir(async () => { await http.put(`/admin/utilisateurs/${u.id}`, { [NK]: u[NK], email: u.email, role: u.role, is_active: !actif }); users.value = liste(unwrap(await http.get('/admin/utilisateurs'))); }, 'Compte mis à jour.');
}
async function supprimer(u) {
    if (!(await confirmer(`Supprimer définitivement le compte de ${u.name} (${u.email}) ? Cette action est irréversible. Pour seulement bloquer l’accès, utilisez plutôt « Désactiver ».`))) return;
    agir(async () => { await http.delete(`/admin/utilisateurs/${u.id}`); users.value = liste(unwrap(await http.get('/admin/utilisateurs'))); }, 'Compte supprimé.');
}
onMounted(() => { if (estAdmin()) users.value = [], http.get('/admin/utilisateurs').then((r) => (users.value = liste(unwrap(r)))).catch((e) => notifier(erreur(e), 'error')); });
</script>
<template>
  <h1>Paramètres</h1>
  <p class="mut aide">Gérez vos informations personnelles et votre mot de passe.<template v-if="estAdmin()"> En tant qu’administrateur, vous gérez aussi les comptes des utilisateurs.</template></p>
  <div v-if="estAdmin()" class="tabs"><button :class="{ on: onglet === 'compte' }" @click="onglet = 'compte'">Mon compte</button>
    <button :class="{ on: onglet === 'utilisateurs' }" @click="onglet = 'utilisateurs'">Utilisateurs</button>
    <button :class="{ on: onglet === 'historique' }" @click="onglet = 'historique'">Historique</button></div>
  <div v-if="onglet === 'compte'" class="grid" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr))">
    <form class="glass" style="padding:18px" @submit.prevent="majProfil"><h2>Mon profil</h2><p class="mut">Votre nom et votre adresse e-mail servent à vous identifier dans l’application.</p>
      <label for="pn">Nom</label><input id="pn" v-model="profil[NK]" required>
      <label for="pe">Adresse e-mail</label><input id="pe" v-model="profil.email" type="email" required>
      <p><button class="primary">Enregistrer</button></p></form>
    <form class="glass" style="padding:18px" @submit.prevent="majMdp"><h2>Mot de passe</h2><p class="mut">Au moins 10 caractères, avec une majuscule, une minuscule et un chiffre.</p>
      <label for="m1">Mot de passe actuel</label><input id="m1" v-model="mdp.current_password" :type="voirMdp ? 'text' : 'password'" autocomplete="current-password" required>
      <label for="m2">Nouveau mot de passe</label><input id="m2" v-model="mdp.password" :type="voirMdp ? 'text' : 'password'" autocomplete="new-password" required>
      <label for="m3">Confirmation</label><input id="m3" v-model="mdp.password_confirmation" :type="voirMdp ? 'text' : 'password'" autocomplete="new-password" required>
      <label class="voir"><input v-model="voirMdp" type="checkbox"> Afficher les mots de passe</label>
      <p><button class="primary">Changer le mot de passe</button></p></form>
  </div>
  <section v-if="estAdmin() && onglet === 'utilisateurs'" style="margin-top:8px"><h2>Gestion des utilisateurs</h2>
    <p class="mut aide">Créez des comptes administrateur ou enseignant. Si vous laissez le mot de passe vide, un mot de passe temporaire est généré et affiché une seule fois : transmettez-le à la personne. Un compte désactivé ne peut plus se connecter et peut être réactivé à tout moment. La suppression, elle, est définitive.</p>
    <form class="glass row" style="padding:16px" @submit.prevent="creer">
      <div class="grow"><label for="un">Nom</label><input id="un" v-model="nouveau[NK]" required></div>
      <div class="grow"><label for="ue">E-mail</label><input id="ue" v-model="nouveau.email" type="email" required></div>
      <div><label for="ur">Rôle</label><select id="ur" v-model="nouveau.role"><option v-for="(l, k) in ROLES" :key="k" :value="k">{{ l }}</option></select></div>
      <div class="grow"><label for="up">Mot de passe (facultatif)</label><input id="up" v-model="nouveau.password" :type="voirNouveau ? 'text' : 'password'" autocomplete="new-password" placeholder="Vide = mot de passe temporaire"><label class="voir"><input v-model="voirNouveau" type="checkbox"> Afficher</label></div>
      <button class="primary" style="align-self:end">Créer le compte</button></form>
    <p v-if="temp" class="glass" style="padding:12px">Mot de passe temporaire (affiché une seule fois) : <strong>{{ temp }}</strong></p>
    <div class="tw"><table><thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Statut</th><th></th></tr></thead><tbody>
      <tr v-for="u in users" :key="u.id"><td>{{ u[NK] }}</td><td>{{ u.email }}</td><td>{{ /admin/i.test(u.role) ? 'Administrateur' : 'Enseignant' }}</td>
        <td><span class="badge" :class="{ publie: u.is_active }">{{ (u.is_active) ? 'Actif' : 'Désactivé' }}</span></td>
        <td><div class="row"><button :disabled="u.id === auth.user?.id" @click="basculer(u)">{{ u.is_active ? 'Désactiver' : 'Réactiver' }}</button>
          <button class="danger" :disabled="u.id === auth.user?.id" @click="supprimer(u)">Supprimer</button></div></td></tr>
      <tr v-if="!users.length"><td colspan="5" class="mut">Aucun utilisateur à afficher.</td></tr></tbody></table></div>
  </section>
  <Historique v-if="estAdmin() && onglet === 'historique'" />
</template>
