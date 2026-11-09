<script setup>
import { ref, computed, onMounted } from 'vue';
import { http, unwrap, liste, erreur, notifier, confirmer, estAdmin, idDe, statutDe, STATUT } from '../api';
const admin = estAdmin();
const items = ref([]); const edition = ref(null); const champs = ref({}); const busy = ref(false);
const ecs = ref([]); const annees = ref([]); const profs = ref([]); const autos = ref([]);
const nouveau = ref({ ec: '', annee: '' }); const prof = ref(''); const resp = ref(false);
const FIELDS = [['objectifs', 'Objectifs'], ['programme', 'Contenu'], ['bibliographie', 'Bibliographie']];

const garde = (f) => async (...a) => { busy.value = true; try { await f(...a); } catch (e) { notifier(erreur(e), 'error'); } finally { busy.value = false; } };
const recharger = garde(async () => { items.value = liste(unwrap(await http.get(admin ? '/admin/syllabus' : '/mes-syllabus'))); });
onMounted(async () => {
    recharger();
    if (!admin) return;
    garde(async () => {
        ecs.value = liste(unwrap(await http.get('/admin/ecs'))); annees.value = liste(unwrap(await http.get('/annees')));
        profs.value = liste(unwrap(await http.get('/admin/utilisateurs'))).filter((u) => u.role === 'enseignant' && u.is_active);
    })();
});
// Valeur réelle du statut « publié » ou « archivé » telle que l'API la renvoie.
const brut = (cible) => items.value.find((s) => statutDe(s) === cible)?.statut ?? { publie: 'publie', archive: 'archive', brouillon: 'brouillon' }[cible];

const ouvrir = garde(async (s) => {
    const d = unwrap(await http.get(`/syllabus/${idDe(s)}`)); edition.value = d;
    champs.value = Object.fromEntries(FIELDS.map(([k]) => [k, d[k] ?? '']));
    if (admin) autos.value = liste(unwrap(await http.get(`/admin/syllabus/${idDe(s)}/autorisations`)));
});
const enregistrer = garde(async () => {
    const corps = Object.fromEntries(Object.entries(champs.value).filter(([k, v]) => v !== (edition.value[k] ?? '')));
    if (!Object.keys(corps).length) return notifier('Aucune modification à enregistrer.');
    await http.put(`/syllabus/${idDe(edition.value)}`, corps); notifier('Syllabus enregistré.'); edition.value = null; await recharger();
});
const creer = garde(async () => { await http.post('/admin/syllabus', nouveau.value); notifier('Syllabus créé en brouillon.'); nouveau.value = { ec: '', annee: '' }; await recharger(); });
async function statut(s, valeur, texte) {
    if (!(await confirmer(texte))) return;
    await garde(async () => { await http.put(`/syllabus/${idDe(s)}`, { statut: brut(valeur) }); notifier('Statut mis à jour.'); await recharger(); })();
}
async function supprimer(s) {
    if (!(await confirmer(`Supprimer le brouillon « ${s.ec?.intitule} » ?`))) return;
    await garde(async () => { await http.delete(`/admin/syllabus/${idDe(s)}`); notifier('Brouillon supprimé.'); await recharger(); })();
}
const attribuer = garde(async () => {
    await http.post(`/admin/syllabus/${idDe(edition.value)}/autorisations`, { user: prof.value, est_responsable: resp.value });
    notifier('Enseignant autorisé.'); autos.value = liste(unwrap(await http.get(`/admin/syllabus/${idDe(edition.value)}/autorisations`)));
});
async function retirer(a) {
    if (!(await confirmer('Retirer le droit de modification à cet enseignant ?'))) return;
    await garde(async () => { await http.delete(`/admin/syllabus/${idDe(edition.value)}/autorisations/${a.user?.id ?? a.user_id ?? a.id}`); notifier('Droit retiré.'); autos.value = liste(unwrap(await http.get(`/admin/syllabus/${idDe(edition.value)}/autorisations`))); })();
}
const peut = computed(() => edition.value?.permissions?.modifier);
</script>
<template>
  <section v-if="!edition">
    <p v-if="!admin" class="mut">Vous ne pouvez modifier que les syllabus qui vous ont été attribués.</p>
    <form v-if="admin" class="glass row" style="padding:14px;margin-bottom:14px" @submit.prevent="creer">
      <div class="grow"><label for="se">EC</label><select id="se" v-model="nouveau.ec" required><option value="" disabled>Choisir un EC</option><option v-for="e in ecs" :key="idDe(e)" :value="idDe(e)">{{ e.code }} {{ e.intitule }}</option></select></div>
      <div class="grow"><label for="sa">Année universitaire</label><select id="sa" v-model="nouveau.annee" required><option value="" disabled>Choisir une année</option><option v-for="a in annees" :key="idDe(a)" :value="idDe(a)">{{ a.libelle }}</option></select></div>
      <button class="primary" style="align-self:end" :disabled="busy">Créer un syllabus</button></form>
    <div class="tw"><table><thead><tr><th>EC</th><th>Année</th><th>Enseignants</th><th>Statut</th><th>Actions</th></tr></thead><tbody>
      <tr v-for="s in items" :key="idDe(s)"><td>{{ s.ec?.code }} {{ s.ec?.intitule }}</td><td>{{ s.annee_universitaire?.libelle }}</td><td>{{ (s.enseignants ?? []).map((e) => e.nom).join(', ') }}</td>
        <td><span class="badge" :class="statutDe(s)">{{ s.statut_libelle ?? STATUT[statutDe(s)] }}</span></td>
        <td class="row"><button :disabled="busy || statutDe(s) === 'archive'" @click="ouvrir(s)">Modifier</button>
          <template v-if="admin"><button v-if="statutDe(s) === 'brouillon'" class="primary" @click="statut(s, 'publie', 'Publier ce syllabus ?')">Publier</button>
            <button v-if="statutDe(s) === 'publie'" @click="statut(s, 'archive', 'Archiver ce syllabus ?')">Archiver</button>
            <button v-if="statutDe(s) === 'brouillon'" class="danger" @click="supprimer(s)">Supprimer</button></template></td></tr>
      <tr v-if="!items.length"><td colspan="5" class="mut">Aucun syllabus accessible pour le moment.</td></tr></tbody></table></div>
  </section>
  <section v-else>
    <div class="row"><button @click="edition = null">← Retour</button><h2 class="grow">{{ edition.ec?.code }} : {{ edition.ec?.intitule }}</h2></div>
    <form @submit.prevent="enregistrer"><template v-for="[k, t] in FIELDS" :key="k"><label :for="'c' + k">{{ t }}</label><textarea :id="'c' + k" v-model="champs[k]" rows="7" :disabled="!peut" /></template>
      <p><button class="primary" :disabled="busy || !peut">Enregistrer les modifications</button></p></form>
    <div v-if="admin" class="glass" style="padding:14px"><h3>Enseignants autorisés à modifier</h3>
      <p v-for="a in autos" :key="JSON.stringify(a)" class="row"><span class="grow">{{ a.user?.name ?? a.name ?? a.nom }} <span v-if="a.est_responsable" class="badge">Responsable</span></span><button class="danger" @click="retirer(a)">Retirer</button></p>
      <p v-if="!autos.length" class="mut">Aucun enseignant n’est encore autorisé.</p>
      <div class="row"><select v-model="prof" class="grow" aria-label="Enseignant"><option value="" disabled>Choisir un enseignant</option><option v-for="u in profs" :key="u.id" :value="u.id">{{ u.name }}</option></select>
        <label style="margin:0"><input v-model="resp" type="checkbox" style="width:auto"> Responsable</label><button class="primary" :disabled="!prof || busy" @click="attribuer">Autoriser</button></div></div>
  </section>
</template>
