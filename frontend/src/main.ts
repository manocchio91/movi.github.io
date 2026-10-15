import { createApp } from 'vue';
import App from './App';
import './styles.css';

const mount = () => createApp(App).mount('#app');

if (import.meta.env.VITE_DEMO === '1') {
  // Build demo per GitHub Pages: dati di esempio al posto del backend.
  const apiBase = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api/v1';
  void import('./demo/mockApi').then(({ installDemoApi }) => {
    installDemoApi(apiBase);
    mount();
  });
} else {
  mount();
}
