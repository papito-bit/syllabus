<script setup>
import { ref } from 'vue';
import { estAdmin } from '../api';
import Maquettes from './Maquettes.vue';
import Syllabus from './Syllabus.vue';
const onglet = ref('maquettes'); const admin = estAdmin();
</script>
<template>
  <h1>Modifications</h1>
  <p class="mut aide">Gérez ici le contenu pédagogique : les maquettes (unités d’enseignement, matières, volumes horaires, crédits) et les syllabus. Les actions proposées dépendent de votre rôle.</p>
  <div class="tabs"><button :class="{ on: onglet === 'maquettes' }" @click="onglet = 'maquettes'">Maquettes pédagogiques</button>
    <button :class="{ on: onglet === 'syllabus' }" @click="onglet = 'syllabus'">Syllabus</button></div>
  <p v-if="onglet === 'maquettes'" class="mut aide">{{ admin ? 'Une maquette est créée en brouillon et reste modifiable. Une fois publiée, elle devient visible du public et passe en lecture seule : dupliquez-la pour la faire évoluer, par exemple pour une nouvelle année.' : 'Vous pouvez consulter les maquettes. Leur modification est réservée aux administrateurs.' }}</p>
  <p v-else class="mut aide">{{ admin ? 'Créez un syllabus pour chaque matière et chaque année, autorisez les enseignants concernés à le modifier, puis publiez-le quand il est prêt.' : 'Voici les syllabus qui vous ont été attribués. Vous pouvez modifier les objectifs, le contenu et la bibliographie ; la publication est réservée à l’administration.' }}</p>
  <Maquettes v-if="onglet === 'maquettes'" />
  <Syllabus v-else />
</template>
