import{d as ae,Q as ne,P as oe,a as le,r as u,W as re,c as p,ak as se,I as r,o as ce,aL as gt,aJ as ie,ad as Nt,b as v,e as E,i as n,w as o,x as G,M as Lt,v as b,a4 as Ct,s as _,j as w,z as T,am as H,k as d,g as c,p as $,ao as ue,A as It,t as de,q as Ut,V as Z,C as ft,f as P,al as yt,ap as Rt,aq as me,N as M,F as ht,h as pe,aM as ve}from"./main-BpYC0Opx.js";import{_ as ge}from"./FullDetailsButton-CTvDn7cT.js";import{_ as Ce}from"./DataTableFilterMenu.vue_vue_type_script_setup_true_lang-B0mV6jdW.js";import{_ as fe}from"./DataTablePageKpis.vue_vue_type_script_setup_true_lang-FMvqfY8m.js";import{c as ye,_ as he,C as be,a as qt,p as _e}from"./contractPartyDetails-C3Dyu0OL.js";import{_ as we}from"./PrintableListReport.vue_vue_type_style_index_0_lang-RRLrNJ2d.js";import{S as $e}from"./services-BTSoRzFj.js";import{u as ke}from"./useContracts-6GWjQpi0.js";import{l as Ve}from"./contractPlaceholders-hryht2sD.js";import{p as Se}from"./printDocument-CFllQaNc.js";import{a as Ae,V as bt}from"./VRow-CmHxDWdY.js";import{V as De}from"./VAlert-BLu3X-k9.js";const Te={class:"headline"},Pe={class:"mb-4"},Ne={class:"d-flex flex-column gap-2"},Le={key:0,class:"custom-cover-page"},Ie=["src"],Ue={key:3,class:"empty-print-page",style:{width:"210mm",height:"297mm",background:"white"}},Qe=ae({__name:"contract_list",props:{contractAudience:{default:"customers"}},setup(tt){const N=tt,f=ne(),et=oe(),{t:g}=le(),_t=()=>({customers:"contracts",employees:"employee-contracts",other:"other-contracts"})[N.contractAudience],{contracts:q,templates:O,isLoading:wt,error:$t,fetchContracts:at,fetchTemplates:zt,addContract:Bt,editContract:Ft,removeContract:jt}=ke(),L=u(""),I=u(null),U=u(null),R=u(!1),W=u("add"),z=u(null),V=u(!1),B=u(!1),F=u(!0),nt=u(!0),Q=u(!0),S=u(!1),s=u(null),ot=re("userData"),xt=p(()=>ot.value?.roles?.includes("super_admin")??!1),lt=p(()=>String(ot.value?.employee?.name??"").trim()||String(ot.value?.name??"").trim()||null),A=u(""),rt=u(null),st=u(!1),ct=u(!1),k=se(),j=u(!1),y=u(null),Y=p(()=>{const t=Number(f.query.serviceId);return Number.isSafeInteger(t)&&t>0?t:null}),it=p(()=>f.query.withoutService==="1"),Et=p(()=>{const t=Number(f.query.customerId);return Number.isSafeInteger(t)&&t>0?t:null}),J=p(()=>typeof f.query.serviceName=="string"?f.query.serviceName:null),kt=p({get:()=>Y.value,set:t=>{et.replace({query:{...f.query,...t?{serviceId:String(t)}:{serviceId:void 0}}})}}),ut=p(()=>({audience:N.contractAudience,...Y.value?{service_id:Y.value}:{},...it.value?{without_service:1}:{}})),Ht=p(()=>J.value?g("pages.contracts.allContracts.allContracts.serviceContracts",{service:J.value}):it.value?g("pages.contracts.allContracts.allContracts.unassignedContracts"):g(dt.value.title)),Mt=p(()=>J.value?g("pages.contracts.allContracts.allContracts.serviceContractsDescription",{service:J.value}):g(dt.value.description)),Ot=p(()=>[...new Set(q.value.map(t=>t.counterparty).filter(t=>t))]),Wt=["draft","active","completed","terminated","cancelled"],K=p(()=>s.value!==null&&ye(s.value)),Vt=u([]),dt=p(()=>({customers:{title:"pages.contracts.allContracts.allContracts.customerContracts",description:"pages.contracts.allContracts.allContracts.customerContractsDescription",report:"pages.contracts.allContracts.allContracts.customerContractsReport"},employees:{title:"pages.contracts.allContracts.allContracts.employeeContracts",description:"pages.contracts.allContracts.allContracts.employeeContractsDescription",report:"pages.contracts.allContracts.allContracts.employeeContractsReport"},other:{title:"pages.contracts.allContracts.allContracts.otherContracts",description:"pages.contracts.allContracts.allContracts.otherContractsDescription",report:"pages.contracts.allContracts.allContracts.otherContractsReport"}})[N.contractAudience]),Qt=t=>({active:"success",draft:"info",pending:"warning",completed:"primary",terminated:"secondary",cancelled:"error"})[t?.toLowerCase()]||"default",mt=p(()=>q.value.filter(t=>{const e=t.contract||t.contract_template?.name||"",l=t.counterparty||"",i=e.toLowerCase().includes(L.value.toLowerCase())||l.toLowerCase().includes(L.value.toLowerCase())||(t.contract_number||"").toLowerCase().includes(L.value.toLowerCase()),x=!I.value||t.counterparty===I.value,vt=!U.value||t.status===U.value;return i&&x&&vt})),St=p(()=>[{title:g("common.messages.contractNumber"),key:"contract_number",sortable:!0},{title:g("common.messages.contract"),key:"contract",sortable:!0},{title:g("common.fields.type"),key:"type",sortable:!0},{title:g("pages.contracts.allContracts.allContracts.counterparty"),key:"counterparty",sortable:!0},{title:g("common.messages.services"),key:"service",sortable:!1},{title:g("common.fields.startDate"),key:"start_date",sortable:!0},{title:g("common.fields.status"),key:"status",sortable:!0},{title:g("common.messages.actions"),key:"actions",sortable:!1}]),Yt=t=>[t.contract_number,t.contract||t.contract_template?.name,t.type||t.contract_template?.type,t.counterparty,t.services?.map(e=>e.service?.name||e.service?.type).filter(Boolean).join(", ")||"—",t.start_date,t.status],m=u(null),D=p(()=>s.value?.snapshot_data?.document_design),At=p(()=>{const t=s.value?.contract||s.value?.contract_template?.name||r("pages.contracts.allContracts.allContracts.untitledContract"),e=s.value?.snapshot_data?.template_version;return e?.version_number?[t,e.name,`v${e.version_number}`,e.country_name,e.language_name].filter(Boolean).join(" - "):t}),C=p(()=>({brandName:m.value?.original_company_name||m.value?.short_company_name||m.value?.website_title||"Company",logo:m.value?.website_logo_url||void 0,address:m.value?.physical_address||void 0,phone:m.value?.phone||void 0,website:m.value?.website_address||void 0,email:m.value?.company_email||void 0,licence:m.value?.business_licence||void 0,signatureUrl:m.value?.company_signature_url||void 0,letterheadUrl:D.value?.letterhead_url||m.value?.contract_letterhead_url||void 0,letterheadSafeX:D.value?.safe_x??m.value?.contract_letterhead_safe_x,letterheadSafeY:D.value?.safe_y??m.value?.contract_letterhead_safe_y,letterheadSafeWidth:D.value?.safe_width??m.value?.contract_letterhead_safe_width,letterheadSafeHeight:D.value?.safe_height??m.value?.contract_letterhead_safe_height})),X=t=>{const e=t.content,l=Ve(e);if(l)return[l];const i=Ut(t.start_date),x=t.contract||t.contract_template?.name||r("pages.contracts.allContracts.allContracts.untitledContract");return[`
    <h1>${r("pages.contracts.shared.contractAgreement")}</h1>
    <p>${r("pages.contracts.shared.fallbackOpening")} <strong>${C.value.brandName}</strong> ${r("pages.contracts.shared.and")} <strong>${t.counterparty}</strong> on <strong>${i}</strong>.</p>

    <h2>1. ${r("pages.contracts.shared.contractParties")}</h2>
    <p><strong>${r("pages.contracts.shared.serviceProvider")}:</strong> ${C.value.brandName}, ${r("pages.contracts.shared.locatedAt")} ${C.value.address||r("pages.contracts.shared.registeredCompanyAddress")}</p>
    <p><strong>${r("pages.contracts.shared.client")}:</strong> ${t.counterparty}</p>

    <h2>2. ${r("pages.contracts.shared.contractDetails")}</h2>
    <p><strong>${r("pages.contracts.shared.contractName")}:</strong> ${x}</p>
    <p><strong>${r("pages.contracts.shared.status")}:</strong> ${t.status}</p>
    <p><strong>${r("pages.contracts.shared.startDate")}:</strong> ${i}</p>
    `,`
    <h2>3. ${r("pages.contracts.shared.termsAndConditions")}</h2>
    <p>${r("pages.contracts.shared.partiesAgree")}</p>
    <ol>
      <li>${r("pages.contracts.shared.obligationsTerm")}</li>
      <li>${r("pages.contracts.shared.changesTerm")}</li>
      <li>${r("pages.contracts.shared.durationTerm")}</li>
    </ol>

    <h2>4. ${r("pages.contracts.shared.termination")}</h2>
    <p>${r("pages.contracts.shared.terminationNotice")}</p>
    `]},Jt=async()=>{await Se({bodyClass:"printing-table-contracts",pageStyle:"size: A4 landscape; margin: 12mm;"})},pt=async t=>{try{st.value=!0;let e=t;try{e=await gt.getById(t.id)}catch(l){console.warn("Failed to refresh the contract before printing, using the listed row.",l)}if(!e.contract_template&&e.template_id){const l=await ve.getById(e.template_id);e.contract_template={id:l.id,name:l.name,type:l.type,cover_text:l.cover_text,content:l.content}}s.value=e,A.value=String(e.company_signatory_name??"").trim()||lt.value||"",B.value=!1,F.value=!0,S.value=!1,V.value=!0}catch(e){console.error("Failed to load contract template:",e),k.warning(r("pages.contracts.allContracts.allContracts.failedToLoadContractTemplateUsingDefaultContent")),s.value=t,A.value=String(t.company_signatory_name??"").trim()||lt.value||"",B.value=!1,F.value=!0,S.value=!1,V.value=!0}finally{st.value=!1}},Kt=()=>{if(!rt.value||!s.value)return;const t=window.open("","_blank","width=900,height=1100");if(!t){k.warning(r("common.messages.couldNotOpenPrintWindowPleaseAllowPopUpsForThisSite"));return}const e=Array.from(document.querySelectorAll('style, link[rel="stylesheet"]')).map(i=>i.outerHTML).join(`
`),l=rt.value.innerHTML;t.document.open(),t.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>${s.value.contract_number} - ${s.value.contract||s.value.contract_template?.name||r("pages.contracts.allContracts.allContracts.untitledContract")}</title>
        ${e}
        <style>
          @media print {
            @page {
              size: A4;
              margin: 0 !important;
            }
            body {
              margin: 0;
              padding: 0;
              background: #fff;
              -webkit-print-color-adjust: exact !important;
              print-color-adjust: exact !important;
            }
            .contract-cover {
              position: relative !important;
              width: 210mm !important;
              height: 297mm !important;
              max-width: none !important;
              margin: 0 !important;
              box-shadow: none !important;
              page-break-after: always !important;
              break-after: page !important;
              display: flex !important;
            }
            .custom-cover-page {
              position: relative !important;
              width: 210mm !important;
              height: 297mm !important;
              margin: 0 !important;
              page-break-after: always !important;
              break-after: page !important;
              overflow: hidden !important;
            }
            .custom-cover-page img { width: 100% !important; height: 100% !important; object-fit: cover !important; display: block !important; }
            .contract-page-container {
              position: relative !important;
              width: 210mm !important;
              height: 296mm !important;
              max-height: 296mm !important;
              margin: 0 !important;
              padding: 16mm 18mm 12mm 18mm !important;
              box-shadow: none !important;
              page-break-after: always !important;
              break-after: page !important;
              box-sizing: border-box !important;
              display: flex !important;
              flex-direction: column !important;
              justify-content: space-between !important;
              overflow: hidden !important;
            }
            .empty-print-page {
              width: 210mm !important;
              height: 297mm !important;
              page-break-after: always !important;
              break-after: page !important;
              background: #ffffff !important;
              display: block !important;
            }
            /* Additional print styles for contract content */
            .contract-content {
              font-family: 'Vazirmatn', 'Noto Sans Arabic', Tahoma, sans-serif;
              line-height: 1.6;
              color: #333;
            }
            .contract-content h1,
            .contract-content h2,
            .contract-content h3 {
              color: #222;
              margin-top: 1.5em;
              margin-bottom: 0.5em;
            }
            .contract-content p {
              margin-bottom: 1em;
            }
            .contract-content ul,
            .contract-content ol {
              margin-bottom: 1em;
              padding-inline-start: 2em;
            }
            .contract-content table {
              width: 100%;
              border-collapse: collapse;
              margin: 1em 0;
            }
            .contract-content th,
            .contract-content td {
              border: 1px solid #ddd;
              padding: 8px;
              text-align: left;
            }
            .contract-content th {
              background-color: #f2f2f2;
            }
          }
        </style>
      </head>
      <body>
        <div class="multi-page-print-wrapper">
          ${l}
        </div>
        <script>
          window.onload = () => {
            const pages = document.querySelectorAll('.contract-page-container');
            pages.forEach(page => {
              let zoom = 1.0;
              while (page.scrollHeight > page.clientHeight && zoom > 0.80) {
                zoom -= 0.02;
                page.style.zoom = zoom;
              }
            });

            const images = document.images;
            let loaded = 0;
            if (images.length === 0) {
              triggerPrint();
              return;
            }
            const checkDone = () => {
              loaded++;
              if (loaded === images.length) triggerPrint();
            };
            for (let i = 0; i < images.length; i++) {
              if (images[i].complete) {
                checkDone();
              } else {
                images[i].addEventListener('load', checkDone);
                images[i].addEventListener('error', checkDone);
              }
            }
          };
          function triggerPrint() {
            setTimeout(() => {
              window.focus();
              window.print();
              window.close();
            }, 300);
          }
        <\/script>
      </body>
    </html>
  `),t.document.close(),V.value=!1},Dt=()=>{W.value="add",z.value={id:0,contract_number:"",contract:"",status:"active",start_date:new Date().toISOString().slice(0,10),counterparty:"",is_cancelled:!1,template_id:O.value[0]?.id||0,company_id:null},R.value=!0},Xt=t=>{W.value="edit",z.value={...t},R.value=!0},Tt=t=>t.contract||t.contract_template?.name||r("pages.contracts.allContracts.allContracts.unnamedContract"),Pt=(t,e)=>{y.value={type:t,contract:e},j.value=!0},Gt=async()=>{const t=y.value;if(t){j.value=!1;try{t.type==="delete"?(await jt(t.contract.id),k.success(r("pages.contracts.allContracts.allContracts.contractDeleted"))):(ct.value=!0,await gt.cancel(t.contract.id),await at(ut.value),k.success(r("pages.contracts.allContracts.allContracts.contractCancelled")))}catch(e){console.error(`Failed to ${t.type} contract:`,e),k.error(r(t.type==="delete"?"pages.contracts.allContracts.allContracts.failedToDeleteContractPleaseTryAgain":"pages.contracts.allContracts.allContracts.failedToCancelContractPleaseTryAgain"))}finally{ct.value=!1,y.value=null}}},Zt=t=>{Pt("cancel",t)},te=t=>{Pt("delete",t)},ee=async t=>{try{W.value==="add"?await Bt(t):z.value&&await Ft(z.value.id,t),R.value=!1,k.success(r("pages.contracts.allContracts.allContracts.contractSaved"))}catch{k.error(r("pages.contracts.allContracts.allContracts.failedToSaveContractPleaseTryAgain"))}};return ce(async()=>{await Promise.all([at(ut.value),zt()]),O.value=O.value.filter(l=>N.contractAudience==="employees"?l.type_slug==="employment":N.contractAudience==="customers"?l.type_slug==="customer":l.type_slug!=="employment"&&l.type_slug!=="customer");const t=Number(f.query.employeeId);if(N.contractAudience==="employees"&&f.query.print==="1"&&t){const l=q.value.find(i=>i.employee_id===t&&!i.is_cancelled);l?await pt(l):k.warning(r("pages.contracts.allContracts.allContracts.noActiveEmploymentContractFound"))}f.query.action==="create"&&(Number(f.query.companyId)||Et.value)&&(Dt(),await et.replace({name:_t()}));const e=Number(f.query.contractId);if(f.query.action==="print"&&e){const l=q.value.find(i=>i.id===e)??await gt.getById(e);await pt(l),await et.replace({name:_t()})}else if(e){const l=q.value.find(i=>i.id===e);l&&(L.value=l.contract_number||l.counterparty||l.contract)}try{const l=await $e.getAll();Vt.value=l.data.filter(i=>i.status==="active").map(i=>({title:i.name||i.type,value:i.id}))}catch{}try{m.value=(await ie.getCompanyProfile()).data}catch{}}),Nt([Y,it],()=>at(ut.value)),Nt(lt,t=>{V.value&&!A.value&&t&&(A.value=t)}),(t,e)=>{const l=fe,i=Ce,x=ge,vt=ue;return v(),E(ht,null,[n(l,{items:mt.value,title:Ht.value,description:Mt.value,icon:"tabler-file-description"},null,8,["items","title","description"]),n(Z,null,{default:o(()=>[n(G,null,{default:o(()=>[n(Ae,{align:"center",class:"table-toolbar"},{default:o(()=>[n(bt,{cols:"12",md:"4",lg:"6"},{default:o(()=>[n(Lt,{modelValue:L.value,"onUpdate:modelValue":e[0]||(e[0]=a=>L.value=a),class:"data-table-search","prepend-inner-icon":"tabler-search",placeholder:t.$t("pages.contracts.allContracts.allContracts.searchContracts"),density:"compact",clearable:"","hide-details":""},null,8,["modelValue","placeholder"])]),_:1}),tt.contractAudience==="customers"?(v(),b(bt,{key:0,cols:"12",md:"3"},{default:o(()=>[n(Ct,{modelValue:kt.value,"onUpdate:modelValue":e[1]||(e[1]=a=>kt.value=a),items:Vt.value,label:t.$t("common.navigation.services"),density:"compact",clearable:"","hide-details":""},null,8,["modelValue","items","label"])]),_:1})):_("",!0),n(bt,{cols:"12",md:"5",lg:"3",class:"table-toolbar-actions d-flex flex-nowrap justify-end gap-2"},{default:o(()=>[n(i,{active:!!(I.value||U.value),onReset:e[4]||(e[4]=a=>{I.value=null,U.value=null})},{default:o(()=>[n(Ct,{modelValue:I.value,"onUpdate:modelValue":e[2]||(e[2]=a=>I.value=a),items:Ot.value,label:t.$t("pages.contracts.allContracts.allContracts.filterByCounterparty"),density:"compact",clearable:"","hide-details":""},null,8,["modelValue","items","label"]),n(Ct,{modelValue:U.value,"onUpdate:modelValue":e[3]||(e[3]=a=>U.value=a),items:Wt,label:t.$t("common.messages.filterByStatus"),density:"compact",clearable:"","hide-details":""},null,8,["modelValue","label"])]),_:1},8,["active"]),n(w,{icon:"tabler-printer",variant:"text",class:"flex-shrink-0",onClick:Jt},{default:o(()=>[n(T,{icon:"tabler-printer"}),n(H,{activator:"parent",location:"top"},{default:o(()=>[d(c(t.$t("pages.contracts.allContracts.allContracts.printTable")),1)]),_:1})]),_:1}),n(w,{color:"primary",class:"flex-shrink-0",loading:$(wt),onClick:Dt},{default:o(()=>[n(T,{icon:"tabler-plus",class:"me-1"}),d(" "+c(t.$t("pages.contracts.allContracts.allContracts.addContract")),1)]),_:1},8,["loading"])]),_:1})]),_:1})]),_:1}),$($t)?(v(),b(De,{key:0,type:"error",class:"ma-4"},{default:o(()=>[d(c($($t)),1)]),_:1})):_("",!0),n(vt,{headers:St.value,items:mt.value,"item-value":"id",class:"text-no-wrap","mobile-breakpoint":"sm",loading:$(wt),"loading-text":t.$t("pages.contracts.allContracts.allContracts.loadingContracts"),style:{"overflow-x":"auto"}},{"item.contract":o(({item:a})=>[d(c(a.contract||a.contract_template?.name||t.$t("pages.contracts.allContracts.allContracts.untitledContract"))+" ",1),a.is_cancelled?(v(),b(It,{key:0,color:"error",size:"x-small",class:"ms-2"},{default:o(()=>[d(c(t.$t("common.statuses.cancelled")),1)]),_:1})):_("",!0)]),"item.counterparty":o(({item:a})=>[d(c(a.counterparty||t.$t("pages.contracts.allContracts.allContracts.unknownCounterparty")),1)]),"item.service":o(({item:a})=>[d(c(a.services?.map(h=>h.service?.name||h.service?.type).filter(Boolean).join(", ")||"—"),1)]),"item.start_date":o(({item:a})=>[d(c(("formatLocalizedDate"in t?t.formatLocalizedDate:$(Ut))(a.start_date)),1)]),"item.status":o(({item:a})=>[n(It,{color:Qt(a.status),size:"small",class:"font-weight-medium"},{default:o(()=>[d(c(("translateBackendValue"in t?t.translateBackendValue:$(de))(a.status||"unknown")),1)]),_:2},1032,["color"])]),"item.actions":o(({item:a})=>[n(x,{item:a,title:a.contract_number||a.contract},null,8,["item","title"]),!a.is_cancelled&&["draft","active"].includes(a.status)?(v(),b(w,{key:0,icon:"tabler-ban",variant:"text",size:"small",loading:ct.value,class:"me-2",color:"error",onClick:h=>Zt(a)},{default:o(()=>[n(T,{icon:"tabler-ban"}),n(H,{activator:"parent",location:"top"},{default:o(()=>[d(c(t.$t("pages.contracts.allContracts.allContracts.cancelContract")),1)]),_:1})]),_:1},8,["loading","onClick"])):_("",!0),a.status==="draft"&&!a.is_cancelled?(v(),b(w,{key:1,icon:"tabler-edit",variant:"text",size:"small",class:"me-2",onClick:h=>Xt(a)},{default:o(()=>[n(T,{icon:"tabler-edit"}),n(H,{activator:"parent",location:"top"},{default:o(()=>[d(c(t.$t("pages.contracts.allContracts.allContracts.editContract")),1)]),_:1})]),_:1},8,["onClick"])):_("",!0),a.status==="draft"&&!a.is_cancelled?(v(),b(w,{key:2,icon:"tabler-trash",variant:"text",size:"small",class:"me-2",onClick:h=>te(a)},{default:o(()=>[n(T,{icon:"tabler-trash"}),n(H,{activator:"parent",location:"top"},{default:o(()=>[d(c(t.$t("pages.contracts.allContracts.allContracts.deleteContract")),1)]),_:1})]),_:1},8,["onClick"])):_("",!0),n(w,{icon:"tabler-printer",variant:"text",size:"small",loading:st.value,onClick:h=>pt(a)},{default:o(()=>[n(T,{icon:"tabler-printer"}),n(H,{activator:"parent",location:"top"},{default:o(()=>[d(c(t.$t("pages.contracts.allContracts.allContracts.printContract")),1)]),_:1})]),_:1},8,["loading","onClick"])]),_:1},8,["headers","items","loading","loading-text"])]),_:1}),n(we,{title:$(g)(dt.value.report),subtitle:t.$t("pages.contracts.allContracts.allContracts.filteredContractRecords"),columns:St.value.slice(0,-1).map(a=>a.title),rows:mt.value.map(Yt)},null,8,["title","subtitle","columns","rows"]),n(yt,{modelValue:R.value,"onUpdate:modelValue":e[6]||(e[6]=a=>R.value=a),"max-width":"600px",persistent:""},{default:o(()=>[n(Z,null,{default:o(()=>[n(ft,null,{default:o(()=>[P("span",Te,c(W.value==="add"?t.$t("pages.contracts.allContracts.allContracts.addNewContract"):t.$t("pages.contracts.allContracts.allContracts.editContract")),1)]),_:1}),n(G,null,{default:o(()=>[n(he,{contract:z.value||void 0,templates:$(O),audience:tt.contractAudience,onSubmit:ee,onCancel:e[5]||(e[5]=a=>R.value=!1)},null,8,["contract","templates","audience"])]),_:1})]),_:1})]),_:1},8,["modelValue"]),n(yt,{modelValue:j.value,"onUpdate:modelValue":e[8]||(e[8]=a=>j.value=a),"max-width":"460px"},{default:o(()=>[n(Z,null,{default:o(()=>[n(ft,null,{default:o(()=>[d(c(t.$t("pages.contracts.allContracts.allContracts.confirmContractAction")),1)]),_:1}),n(G,null,{default:o(()=>[d(c(y.value?.type==="delete"?t.$t("pages.contracts.allContracts.allContracts.deleteContractConfirmation",{contract:y.value?Tt(y.value.contract):""}):t.$t("pages.contracts.allContracts.allContracts.cancelContractConfirmation",{contract:y.value?Tt(y.value.contract):""})),1)]),_:1}),n(Rt,null,{default:o(()=>[n(me),n(w,{variant:"tonal",color:"secondary",onClick:e[7]||(e[7]=a=>j.value=!1)},{default:o(()=>[d(c(t.$t("common.actions.cancel")),1)]),_:1}),n(w,{color:y.value?.type==="delete"?"error":"warning",onClick:Gt},{default:o(()=>[d(c(y.value?.type==="delete"?t.$t("common.actions.delete"):t.$t("pages.contracts.allContracts.allContracts.cancelContract")),1)]),_:1},8,["color"])]),_:1})]),_:1})]),_:1},8,["modelValue"]),n(yt,{modelValue:V.value,"onUpdate:modelValue":e[16]||(e[16]=a=>V.value=a),"max-width":"480px"},{default:o(()=>[n(Z,null,{default:o(()=>[n(ft,{class:"d-flex align-center gap-2"},{default:o(()=>[n(T,{icon:"tabler-printer"}),P("span",null,c(t.$t("pages.contracts.allContracts.allContracts.printContractOptions")),1)]),_:1}),n(G,null,{default:o(()=>[P("p",Pe,[d(c(t.$t("pages.contracts.allContracts.allContracts.prepare"))+" ",1),P("strong",null,c(s.value?.contract||s.value?.contract_template?.name||t.$t("pages.contracts.allContracts.allContracts.untitledContract")),1),d(" "+c(t.$t("pages.contracts.allContracts.allContracts.forA4PrintingTheDocumentWillIncludeTheCoverPageAndAllContractTerms")),1)]),P("div",Ne,[n(Lt,{modelValue:A.value,"onUpdate:modelValue":e[9]||(e[9]=a=>A.value=a),label:t.$t("pages.contracts.contractform.contractform.signatory"),hint:t.$t(xt.value?"pages.contracts.contractform.contractform.signatoryHint":"pages.contracts.contractform.contractform.signatoryLockedHint"),"persistent-hint":"",density:"compact"},null,8,["modelValue","label","hint"]),n(M,{modelValue:F.value,"onUpdate:modelValue":e[10]||(e[10]=a=>F.value=a),label:t.$t("pages.contracts.allContracts.allContracts.includeFormalSignatureStampSectionAtTheEnd"),density:"compact","hide-details":""},null,8,["modelValue","label"]),n(M,{modelValue:nt.value,"onUpdate:modelValue":e[11]||(e[11]=a=>nt.value=a),label:t.$t("pages.contracts.allContracts.allContracts.useCustomCoverPageFromSettings"),density:"compact","hide-details":""},null,8,["modelValue","label"]),n(M,{modelValue:Q.value,"onUpdate:modelValue":e[12]||(e[12]=a=>Q.value=a),label:t.$t("pages.contracts.allContracts.allContracts.useCustomLetterheadFromSettings"),density:"compact","hide-details":""},null,8,["modelValue","label"]),K.value?(v(),b(M,{key:0,modelValue:S.value,"onUpdate:modelValue":e[13]||(e[13]=a=>S.value=a),label:t.$t("pages.contracts.shared.includePartyDetails"),density:"compact","hide-details":""},null,8,["modelValue","label"])):_("",!0),n(M,{modelValue:B.value,"onUpdate:modelValue":e[14]||(e[14]=a=>B.value=a),label:t.$t("pages.contracts.allContracts.allContracts.addALastFullyEmptyPage"),density:"compact","hide-details":""},null,8,["modelValue","label"])])]),_:1}),n(Rt,{class:"justify-end px-4 pb-4"},{default:o(()=>[n(w,{variant:"outlined",color:"secondary",onClick:e[15]||(e[15]=a=>V.value=!1)},{default:o(()=>[d(c(t.$t("common.actions.cancel")),1)]),_:1}),n(w,{color:"primary",onClick:Kt},{default:o(()=>[d(c(t.$t("pages.contracts.allContracts.allContracts.printA4Document")),1)]),_:1})]),_:1})]),_:1})]),_:1},8,["modelValue"]),P("div",{ref_key:"combinedPrintRef",ref:rt,style:{display:"none"}},[s.value?(v(),E(ht,{key:0},[nt.value&&(D.value?.cover_design_url||m.value?.contract_cover_design_url)?(v(),E("div",Le,[P("img",{src:D.value?.cover_design_url||m.value.contract_cover_design_url,alt:""},null,8,Ie)])):(v(),b(be,{key:1,"contractee-name":s.value.counterparty||"",logo:C.value.logo,"cover-design-url":"","contract-number":s.value.contract_number||"","contract-title":At.value,"cover-description":s.value.cover_text||s.value.contract_template?.cover_text||"","company-website":C.value.website||"","company-address":C.value.address||"","company-email":C.value.email||"","company-number":C.value.phone||""},null,8,["contractee-name","logo","contract-number","contract-title","cover-description","company-website","company-address","company-email","company-number"])),S.value&&K.value?(v(),b(qt,{key:2,"document-title":t.$t("pages.contracts.shared.contractParties"),"document-number":s.value.contract_number||"",company:{...C.value,letterheadUrl:Q.value?C.value.letterheadUrl:void 0},"party-details":$(_e)(s.value,C.value),"contractee-name":s.value.counterparty||"","page-number-string":t.$t("pages.contracts.allContracts.allContracts.pageNumber",{page:1,total:X(s.value).length+1}),"footer-text":t.$t("pages.contracts.allContracts.allContracts.confidentialAllRightsReserved"),class:"contract-content"},null,8,["document-title","document-number","company","party-details","contractee-name","page-number-string","footer-text"])):_("",!0),(v(!0),E(ht,null,pe(X(s.value),(a,h)=>(v(),b(qt,{key:h,"document-number":s.value.contract_number||"","document-title":At.value,company:{...C.value,letterheadUrl:Q.value?C.value.letterheadUrl:void 0},"html-content":a,"page-number-string":t.$t("pages.contracts.allContracts.allContracts.pageNumber",{page:h+(S.value&&K.value?2:1),total:X(s.value).length+(S.value&&K.value?1:0)}),"show-signature":F.value&&h===X(s.value).length-1,"contractee-name":s.value.counterparty||"","company-signatory-name":A.value||s.value.company_signatory_name||null,"footer-text":t.$t("pages.contracts.allContracts.allContracts.confidentialAllRightsReserved"),class:"contract-content"},null,8,["document-number","document-title","company","html-content","page-number-string","show-signature","contractee-name","company-signatory-name","footer-text"]))),128)),B.value?(v(),E("div",Ue)):_("",!0)],64)):_("",!0)],512)],64)}}});export{Qe as _};
