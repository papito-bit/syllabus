<script setup>
import { useRouter } from 'vue-router';
import { auth, deconnecter, confirmer, notifier } from '../api';
const router = useRouter();
// À renseigner dans le fichier .env du projet (VITE_CONTACT_TEL, VITE_CONTACT_EMAIL, VITE_APP_VERSION).
const e = import.meta.env;
const tel = e.VITE_CONTACT_TEL; const mail = e.VITE_CONTACT_EMAIL; const version = e.VITE_APP_VERSION || 'V1';
async function sortir() {
    if (!(await confirmer('Voulez-vous vraiment vous déconnecter ?'))) return;
    await deconnecter(); notifier('Vous êtes déconnecté.'); router.push('/connexion');
}
</script>
<template>
  <h1>À propos</h1>
  <p style="max-width:70ch"><strong>Gestion Pédagogique</strong> centralise les maquettes pédagogiques (unités d’enseignement, éléments constitutifs, semestres, crédits, volumes horaires) et les syllabus associés, par niveau d’études et par parcours.</p>
  <p class="mut" style="max-width:70ch">Objectif : donner aux étudiants un accès clair aux programmes, et aux équipes pédagogiques un outil fiable pour les tenir à jour.</p>
  <h2>Qui peut faire quoi ?</h2>
  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
    <div class="glass card" style="cursor:default"><h3>Visiteur</h3><p class="mut">Consulte les maquettes et les syllabus publiés, sans compte.</p></div>
    <div class="glass card" style="cursor:default"><h3>Enseignant</h3><p class="mut">Modifie les syllabus qui lui ont été attribués par l’administration.</p></div>
    <div class="glass card" style="cursor:default"><h3>Administrateur</h3><p class="mut">Gère les comptes, les maquettes, les syllabus et leur publication.</p></div>
  </div>
  <h2>Informations</h2>
  <p>Version : <span :class="{ mut: !version }">{{ version || 'à compléter (VITE_APP_VERSION)' }}</span></p>
  <h2>Contact</h2>
  <p>Téléphone : <a v-if="tel" :href="'tel:' + tel">{{ tel }}</a><span v-else class="mut">à compléter (VITE_CONTACT_TEL)</span></p>
  <p>E-mail : <a v-if="mail" :href="'mailto:' + mail">{{ mail }}</a><span v-else class="mut">à compléter (VITE_CONTACT_EMAIL)</span></p>
  <p style="margin-top:28px"><button v-if="auth.user" class="danger" @click="sortir">Déconnexion</button>
    <button v-else class="primary" @click="router.push('/connexion')">Se connecter</button></p>
</template>
