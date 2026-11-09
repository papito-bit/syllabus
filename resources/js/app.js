import '@fontsource/inter/latin-400.css';
import '@fontsource/inter/latin-500.css';
import '@fontsource/inter/latin-600.css';
import '@fontsource/poppins/latin-600.css';
import '@fontsource/poppins/latin-700.css';
import '../css/app.css';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import { auth, charger } from './api';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/connexion', component: () => import('./views/Connexion.vue'), meta: { nu: true } },
        { path: '/', component: () => import('./views/Accueil.vue') },
        { path: '/parametres', component: () => import('./views/Parametres.vue'), meta: { prive: true } },
        { path: '/modifications', component: () => import('./views/Modifications.vue'), meta: { prive: true } },
        { path: '/a-propos', component: () => import('./views/APropos.vue') },
        { path: '/:rest(.*)', redirect: '/' },
    ],
});
router.beforeEach(async (to) => {
    if (!auth.ready) await charger();
    if (to.meta.prive && !auth.user) return '/connexion';
});
const fond = document.createElement('div');
fond.className = 'fond';
fond.innerHTML = '<i></i><i></i><i></i><i></i><i></i>';
fond.style.setProperty('--photo', "url('/fond-ist.jpg')");
document.body.prepend(fond);
createApp(App).use(router).mount('#app');
