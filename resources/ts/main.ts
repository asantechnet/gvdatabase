import { createApp } from "vue";

import App from "@/App.vue";
// import ckeditor from "@ckeditor/ckeditor5-vue";
import * as Api from "@/api";
import { registerPlugins } from "@core/utils/plugins";
import Toast from "vue-toastification";

// Styles
import "@core-scss/template/index.scss";
import "@styles/styles.scss";
import "vue-toastification/dist/index.css";

// Create vue app
const app = createApp(App);

// app.use(ckeditor);

// Register plugins
registerPlugins(app);
app.use(Toast, { timeout: 5000, closeOnClick: true });

// Register API modules globally
app.config.globalProperties.$api = Api;

// Mount vue app
app.mount("#app");
