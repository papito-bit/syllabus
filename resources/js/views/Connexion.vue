<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { auth, connecter, erreur } from '../api';
const router = useRouter();
const email = ref(''); const mdp = ref(''); const voir = ref(false); const msg = ref(''); const busy = ref(false);
// Champs toujours vides à l'arrivée (notamment après une déconnexion).
const vider = () => { email.value = ''; mdp.value = ''; voir.value = false; };
onMounted(vider);
// Les champs sont verrouillés jusqu'au clic : cela empêche le navigateur de les pré-remplir tout seul.
const deverrouiller = (e) => e.target.removeAttribute('readonly');
async function go() {
    msg.value = '';
    if (!email.value || !mdp.value) { msg.value = 'Saisissez votre adresse e-mail et votre mot de passe.'; return; }
    busy.value = true;
    try {
        await connecter(email.value, mdp.value);
        vider();
        auth.bienvenue = true;
        setTimeout(() => { auth.bienvenue = false; router.push('/'); }, 2200);
    } catch (e) { msg.value = erreur(e); } finally { busy.value = false; }
}
</script>
<template>
  <div class="login"><form class="glass" @submit.prevent="go">
    <img class="logo lg" src="/logo-istd.jpg" alt="IST-D"><h1>Gestion Pédagogique</h1><p class="mut">Institut Supérieur de Technologie d’Antsiranana</p>
    <p class="mut">Connectez-vous pour gérer les maquettes et les syllabus. Vous pouvez aussi les consulter sans compte.</p>
    <label for="e">Adresse e-mail</label><input id="e" v-model="email" type="email" autocomplete="off" readonly required @focus="deverrouiller">
    <label for="p">Mot de passe</label><input id="p" v-model="mdp" :type="voir ? 'text' : 'password'" autocomplete="new-password" readonly required @focus="deverrouiller">
    <label class="voir"><input v-model="voir" type="checkbox"> Afficher le mot de passe</label>
    <p v-if="msg" class="err" role="alert">{{ msg }}</p>
    <div class="row" style="margin-top:18px"><button class="primary grow" :disabled="busy">{{ busy ? 'Connexion…' : 'Se connecter' }}</button>
    <button type="button" @click="router.push('/')">Consulter sans compte</button></div>
  </form><footer class="pied">Gestion Pédagogique V1</footer></div>
</template>
