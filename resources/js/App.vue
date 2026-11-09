<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { auth, toasts, confirmation, nomUser, roleLabel, confirmer, deconnecter, notifier, prefs, changerTaille } from './api';
const route = useRoute(); const router = useRouter();
const nu = computed(() => route.meta.nu);
const menu = ref(false);
const initiales = computed(() => (nomUser() || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((m) => m[0].toUpperCase()).join(''));
const fermer = () => { menu.value = false; };
onMounted(() => document.addEventListener('click', fermer));
onBeforeUnmount(() => document.removeEventListener('click', fermer));
const aller = (chemin) => { fermer(); router.push(chemin); };
async function sortir() {
    fermer();
    if (!(await confirmer('Voulez-vous vraiment vous déconnecter ?'))) return;
    await deconnecter(); notifier('Vous êtes déconnecté.'); router.push('/connexion');
}
const liens = [
    ['/', 'Accueil', 'M3 11l9-8 9 8v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z'],
    ['/parametres', 'Paramètres', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19 12l2-1.5-2-3.5-2.4 1a7 7 0 0 0-2.200-1.300L14 3h-4l-.4 2.700A7 7 0 0 0 7.400 7l-2.400-1-2 3.500L5 11a7 7 0 0 0 0 2l-2 1.500 2 3.500 2.400-1a7 7 0 0 0 2.200 1.300L10 21h4l.4-2.700a7 7 0 0 0 2.200-1.300l2.400 1 2-3.500z'],
    ['/modifications', 'Modifications', 'M4 20h4L19 9l-4-4L4 16zM14 6l4 4'],
    ['/a-propos', 'À propos', 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zm0-11v6m0-9v.01'],
];
</script>
<template>
  <router-view v-if="nu" />
  <div v-else class="shell">
    <nav class="side glass" aria-label="Navigation principale">
      <b class="brand"><img class="logo" src="/logo-istd.jpg" alt="IST-D"><span>Gestion Pédagogique</span></b>
      <router-link v-for="[to, nom, d] in liens" :key="to" :to="to">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path :d="d" /></svg>
        <span>{{ nom }}</span>
      </router-link>
    </nav>
    <div class="main">
      <header class="top glass">
        <strong class="brand"><img class="logo sm" src="/logo-istd.jpg" alt="IST-D">Gestion Pédagogique</strong>
        <div class="row">
          <span class="taille" role="group" aria-label="Taille du texte">
            <button :disabled="prefs.taille <= 13" aria-label="Réduire le texte" title="Réduire le texte" @click="changerTaille(-1)">A−</button>
            <button :disabled="prefs.taille >= 19" aria-label="Agrandir le texte" title="Agrandir le texte" @click="changerTaille(1)">A+</button>
          </span>
          <span v-if="auth.user" class="qui">{{ nomUser() }} <span class="badge">{{ roleLabel() }}</span></span>
          <div v-if="auth.user" class="profil" @keydown.esc="fermer">
            <button class="avatar" aria-haspopup="true" :aria-expanded="menu" title="Mon compte" @click.stop="menu = !menu">{{ initiales }}</button>
            <div v-if="menu" class="menu glass" role="menu" @click.stop>
              <div class="menu-tete"><strong>{{ nomUser() }}</strong><span class="mut">{{ roleLabel() }}</span></div>
              <button role="menuitem" @click="aller('/parametres')">Mon profil</button>
              <button role="menuitem" @click="sortir">Déconnexion</button>
            </div>
          </div>
          <button v-else class="primary" @click="router.push('/connexion')">Se connecter</button>
        </div>
      </header>
      <main class="page glass"><router-view :key="route.fullPath" /></main>
      <footer class="pied glass">Gestion Pédagogique V1</footer>
    </div>
  </div>
  <div class="toasts" aria-live="polite"><div v-for="t in toasts" :key="t.id" class="toast glass" :class="t.type">{{ t.message }}</div></div>
  <div v-if="auth.bienvenue" class="veil"><div class="welcome glass"><h1>Bienvenue, {{ nomUser() }} !</h1><p class="mut">Connecté en tant que {{ roleLabel() }}</p></div></div>
  <div v-if="confirmation.open" class="veil" role="dialog" aria-modal="true"><div class="dialog glass">
    <h3 :class="{ err: confirmation.danger }">{{ confirmation.danger ? 'Attention' : 'Confirmation' }}</h3><p>{{ confirmation.texte }}</p><div class="row"><button :class="confirmation.danger ? 'btn-red' : 'primary'" @click="confirmation.ok(true)">Confirmer</button><button @click="confirmation.ok(false)">Annuler</button></div></div></div>
</template>
