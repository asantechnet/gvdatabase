import{n as h}from"./main-DCPOMvwM.js";let s;const E=()=>new Promise(t=>{requestAnimationFrame(()=>requestAnimationFrame(()=>t()))}),x=async({bodyClass:t,pageStyle:p,element:g,hostPadding:v,beforePrint:y,afterPrint:b})=>{s?.(),await y?.(),document.dispatchEvent(new Event("app:before-print"));const w=t.startsWith("printing-table-"),d=v??(w?/(?:^|;)\s*margin\s*:\s*([^;]+)/i.exec(p)?.[1]?.trim():void 0),f=d?p.replace(/margin\s*:[^;]*;?/i,"margin: 0;"):p,r=document.createElement("style");r.dataset.appPrintPage=t,r.textContent=`@page { ${f} }`,document.head.appendChild(r),document.body.classList.add(t);let i;const l=g??(t.startsWith("printing-table-")?document.querySelector(".list-print-report"):null);if(l){await h(),i=document.createElement("div"),i.id="app-print-host",i.dir=document.documentElement.dir||"ltr";const e=l.cloneNode(!0);if(t==="printing-contract-document"){const o=".contract-cover, .custom-cover-page, .contract-page-container, .empty-print-page";e.matches(o)&&e.classList.add("print-page"),e.querySelectorAll(o).forEach(a=>a.classList.add("print-page"))}i.appendChild(e),document.body.appendChild(i),document.body.classList.add("printing-isolated-document"),r.textContent+=`
      @media screen { #app-print-host { position: fixed; inset: 0; visibility: hidden; pointer-events: none; } }
      @media print {
        body.printing-isolated-document { margin: 0 !important; padding: 0 !important; block-size: auto !important; overflow: visible !important; background: white !important; }
        body.printing-isolated-document > :not(#app-print-host) { display: none !important; }
        body.printing-isolated-document #app-print-host { display: block !important; position: static !important; color: #222; background: white; --v-scrollbar-offset: 0px; }
        body.printing-isolated-document #app-print-host > * { display: block !important; margin: 0 !important; }
        body.printing-isolated-document #app-print-host > :not(.print-page):not(.payslip-container) { position: static !important; }
        ${d?`body.printing-isolated-document #app-print-host { padding: ${d} !important; box-sizing: border-box; }`:""}
      }
      /*
       * Vuetify's overlay scroll strategy pins <html> to the viewport
       * (position: fixed; width/height: 100%) and clips <body> (overflow
       * hidden) for as long as a dialog is open. Every print path opens a
       * dialog, so without overriding that lock the print engine can only lay
       * out a single viewport-height page and the document is truncated to its
       * first screen. These selectors are global by necessity (html cannot be
       * namespaced by a body class) but live only in this temporary sheet,
       * which is removed once the print job finishes.
       */
      @media print {
        html.v-overlay-scroll-blocked {
          position: static !important;
          inset: auto !important;
          inline-size: auto !important;
          block-size: auto !important;
          overflow: visible !important;
        }
        .v-overlay-scroll-blocked:not(html) { overflow-y: visible !important; }
        .v-overlay-scroll-blocked { padding-inline-end: 0 !important; }
        html:has(body.printing-isolated-document) { overflow: visible !important; }
      }`}let m,c=!1;const n=()=>{c||(c=!0,window.removeEventListener("afterprint",n),window.clearTimeout(m),document.body.classList.remove(t),r.remove(),i?.remove(),document.body.classList.remove("printing-isolated-document"),b?.(),s===n&&(s=void 0))};s=n,window.addEventListener("afterprint",n,{once:!0});try{await h(),await document.fonts.ready;const e=Array.from((i??document).querySelectorAll("img"));await Promise.all(e.map(o=>o.complete?Promise.resolve():new Promise(a=>{const k=window.setTimeout(a,8e3),u=()=>{window.clearTimeout(k),a()};o.addEventListener("load",u,{once:!0}),o.addEventListener("error",u,{once:!0})}))),await E(),window.print()}catch(e){throw n(),e}c||(m=window.setTimeout(n,6e4))};export{x as p};
