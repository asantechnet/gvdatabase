import { createApp } from "vue";

import App from "@/App.vue";
// import ckeditor from "@ckeditor/ckeditor5-vue";
import * as Api from "@/api";
import { registerPlugins } from "@core/utils/plugins";

// Styles
import "@core-scss/template/index.scss";
import "@styles/styles.scss";

// Create vue app
const app = createApp(App);

// app.use(ckeditor);

// Register plugins
registerPlugins(app);

// Register API modules globally
app.config.globalProperties.$api = Api;

// Mount vue app
app.mount("#app");
