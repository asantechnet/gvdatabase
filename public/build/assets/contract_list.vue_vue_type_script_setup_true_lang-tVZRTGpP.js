import{d as Zt,P as te,O as ee,a as ae,r as u,ai as ne,c as p,I as s,o as le,ab as oe,b as v,e as E,i as n,w as l,x as J,L as re,v as h,a3 as pt,s as b,j as w,z as S,ak as M,k as d,g as c,p as $,am as se,A as Dt,t as ce,q as Pt,V as Z,C as vt,f as T,aj as gt,an as Nt,ao as ie,M as R,F as Ct,h as ue}from"./main-BCoPqZcH.js";import{_ as de}from"./FullDetailsButton-I6CaOyu1.js";import{_ as me}from"./DataTableFilterMenu.vue_vue_type_script_setup_true_lang-N86BQicV.js";import{_ as pe}from"./DataTablePageKpis.vue_vue_type_script_setup_true_lang-DFPz_agC.js";import{c as ve,a as ge,b as Ce,_ as fe,C as ye,d as It,p as he}from"./useContractPrintHeader-DjCdATzO.js";import{C as ft}from"./contracts-B3zwVLEc.js";import{C as be}from"./contractTemplate-NlgQSXpm.js";import{S as _e}from"./setting-Cmhw8dkK.js";import{_ as we}from"./PrintableListReport.vue_vue_type_style_index_0_lang-DlHt-pq0.js";import{S as $e}from"./services-CM0-6d--.js";import{u as ke}from"./useContracts-C3abgtCw.js";import{l as Ve}from"./contractPlaceholders-hryht2sD.js";import{p as Ae}from"./printDocument-VtCFD-dB.js";import{a as Se,V as yt}from"./VRow-CQoWmHi3.js";import{V as Te}from"./VAlert-BV2KvAp5.js";const De={class:"headline"},Pe={class:"mb-4"},Ne={class:"d-flex flex-column gap-2"},Ie={key:0,class:"custom-cover-page"},Le=["src"],Ue={key:3,class:"empty-print-page",style:{width:"210mm",height:"297mm",background:"white"}},Xe=Zt({__name:"contract_list",props:{contractAudience:{default:"customers"}},setup(tt){const D=tt,f=te(),et=ee(),{t:g}=ae(),ht=()=>({customers:"contracts",employees:"employee-contracts",other:"other-contracts"})[D.contractAudience],{contracts:z,templates:O,isLoading:bt,error:_t,fetchContracts:at,fetchTemplates:Lt,addContract:Ut,editContract:Rt,removeContract:zt}=ke(),P=u(""),N=u(null),I=u(null),L=u(!1),W=u("add"),q=u(null),U=u(!1),B=u(!1),F=u(!0),nt=u(!0),Y=u(!0),V=u(!1),H=u(!1),o=u(null),lt=u(null),ot=u(!1),rt=u(!1),k=ne(),j=u(!1),_=u(null),K=p(()=>{const t=Number(f.query.serviceId);return Number.isSafeInteger(t)&&t>0?t:null}),st=p(()=>f.query.withoutService==="1"),qt=p(()=>{const t=Number(f.query.customerId);return Number.isSafeInteger(t)&&t>0?t:null}),Q=p(()=>typeof f.query.serviceName=="string"?f.query.serviceName:null),wt=p({get:()=>K.value,set:t=>{et.replace({query:{...f.query,...t?{serviceId:String(t)}:{serviceId:void 0}}})}}),ct=p(()=>({audience:D.contractAudience,...K.value?{service_id:K.value}:{},...st.value?{without_service:1}:{}})),Bt=p(()=>Q.value?g("pages.contracts.allContracts.allContracts.serviceContracts",{service:Q.value}):st.value?g("pages.contracts.allContracts.allContracts.unassignedContracts"):g(it.value.title)),Ft=p(()=>Q.value?g("pages.contracts.allContracts.allContracts.serviceContractsDescription",{service:Q.value}):g(it.value.description)),Ht=p(()=>[...new Set(z.value.map(t=>t.counterparty).filter(t=>t))]),jt=["draft","active","completed","terminated","cancelled"],X=p(()=>o.value!==null&&ve(o.value)),xt=p(()=>o.value!==null&&ge(o.value)),Et=p(()=>o.value?Ce(o.value):null),$t=u([]),it=p(()=>({customers:{title:"pages.contracts.allContracts.allContracts.customerContracts",description:"pages.contracts.allContracts.allContracts.customerContractsDescription",report:"pages.contracts.allContracts.allContracts.customerContractsReport"},employees:{title:"pages.contracts.allContracts.allContracts.employeeContracts",description:"pages.contracts.allContracts.allContracts.employeeContractsDescription",report:"pages.contracts.allContracts.allContracts.employeeContractsReport"},other:{title:"pages.contracts.allContracts.allContracts.otherContracts",description:"pages.contracts.allContracts.allContracts.otherContractsDescription",report:"pages.contracts.allContracts.allContracts.otherContractsReport"}})[D.contractAudience]),Mt=t=>({active:"success",draft:"info",pending:"warning",completed:"primary",terminated:"secondary",cancelled:"error"})[t?.toLowerCase()]||"default",ut=p(()=>z.value.filter(t=>{const e=t.contract||t.contract_template?.name||"",r=t.counterparty||"",i=e.toLowerCase().includes(P.value.toLowerCase())||r.toLowerCase().includes(P.value.toLowerCase())||(t.contract_number||"").toLowerCase().includes(P.value.toLowerCase()),x=!N.value||t.counterparty===N.value,mt=!I.value||t.status===I.value;return i&&x&&mt})),kt=p(()=>[{title:g("common.messages.contractNumber"),key:"contract_number",sortable:!0},{title:g("common.messages.contract"),key:"contract",sortable:!0},{title:g("common.fields.type"),key:"type",sortable:!0},{title:g("pages.contracts.allContracts.allContracts.counterparty"),key:"counterparty",sortable:!0},{title:g("common.messages.services"),key:"service",sortable:!1},{title:g("common.fields.startDate"),key:"start_date",sortable:!0},{title:g("common.fields.status"),key:"status",sortable:!0},{title:g("common.messages.actions"),key:"actions",sortable:!1}]),Ot=t=>[t.contract_number,t.contract||t.contract_template?.name,t.type||t.contract_template?.type,t.counterparty,t.services?.map(e=>e.service?.name||e.service?.type).filter(Boolean).join(", ")||"—",t.start_date,t.status],m=u(null),A=p(()=>o.value?.snapshot_data?.document_design),Vt=p(()=>{const t=o.value?.contract||o.value?.contract_template?.name||s("pages.contracts.allContracts.allContracts.untitledContract"),e=o.value?.snapshot_data?.template_version;return e?.version_number?[t,e.name,`v${e.version_number}`,e.country_name,e.language_name].filter(Boolean).join(" - "):t}),C=p(()=>({brandName:m.value?.original_company_name||m.value?.short_company_name||m.value?.website_title||"Company",logo:m.value?.website_logo_url||void 0,address:m.value?.physical_address||void 0,phone:m.value?.phone||void 0,website:m.value?.website_address||void 0,email:m.value?.company_email||void 0,licence:m.value?.business_licence||void 0,signatureUrl:m.value?.company_signature_url||void 0,letterheadUrl:A.value?.letterhead_url||m.value?.contract_letterhead_url||void 0,letterheadSafeX:A.value?.safe_x??m.value?.contract_letterhead_safe_x,letterheadSafeY:A.value?.safe_y??m.value?.contract_letterhead_safe_y,letterheadSafeWidth:A.value?.safe_width??m.value?.contract_letterhead_safe_width,letterheadSafeHeight:A.value?.safe_height??m.value?.contract_letterhead_safe_height})),G=t=>{const e=t.content,r=Ve(e);if(r)return[r];const i=Pt(t.start_date),x=t.contract||t.contract_template?.name||s("pages.contracts.allContracts.allContracts.untitledContract");return[`
    <h1>${s("pages.contracts.shared.contractAgreement")}</h1>
    <p>${s("pages.contracts.shared.fallbackOpening")} <strong>${C.value.brandName}</strong> ${s("pages.contracts.shared.and")} <strong>${t.counterparty}</strong> on <strong>${i}</strong>.</p>

    <h2>1. ${s("pages.contracts.shared.contractParties")}</h2>
    <p><strong>${s("pages.contracts.shared.serviceProvider")}:</strong> ${C.value.brandName}, ${s("pages.contracts.shared.locatedAt")} ${C.value.address||s("pages.contracts.shared.registeredCompanyAddress")}</p>
    <p><strong>${s("pages.contracts.shared.client")}:</strong> ${t.counterparty}</p>

    <h2>2. ${s("pages.contracts.shared.contractDetails")}</h2>
    <p><strong>${s("pages.contracts.shared.contractName")}:</strong> ${x}</p>
    <p><strong>${s("pages.contracts.shared.status")}:</strong> ${t.status}</p>
    <p><strong>${s("pages.contracts.shared.startDate")}:</strong> ${i}</p>
    `,`
    <h2>3. ${s("pages.contracts.shared.termsAndConditions")}</h2>
    <p>${s("pages.contracts.shared.partiesAgree")}</p>
    <ol>
      <li>${s("pages.contracts.shared.obligationsTerm")}</li>
      <li>${s("pages.contracts.shared.changesTerm")}</li>
      <li>${s("pages.contracts.shared.durationTerm")}</li>
    </ol>

    <h2>4. ${s("pages.contracts.shared.termination")}</h2>
    <p>${s("pages.contracts.shared.terminationNotice")}</p>
    `]},Wt=async()=>{await Ae({bodyClass:"printing-table-contracts",pageStyle:"size: A4 landscape; margin: 12mm;"})},dt=async t=>{try{ot.value=!0;let e=t;try{e=await ft.getById(t.id)}catch(r){console.warn("Failed to refresh the contract before printing, using the listed row.",r)}if(!e.contract_template&&e.template_id){const r=await be.getById(e.template_id);e.contract_template={id:r.id,name:r.name,type:r.type,cover_text:r.cover_text,content:r.content}}o.value=e,B.value=!1,F.value=!0,V.value=!1,H.value=!1,U.value=!0}catch(e){console.error("Failed to load contract template:",e),k.warning(s("pages.contracts.allContracts.allContracts.failedToLoadContractTemplateUsingDefaultContent")),o.value=t,B.value=!1,F.value=!0,V.value=!1,H.value=!1,U.value=!0}finally{ot.value=!1}},Yt=()=>{if(!lt.value||!o.value)return;const t=window.open("","_blank","width=900,height=1100");if(!t){k.warning(s("common.messages.couldNotOpenPrintWindowPleaseAllowPopUpsForThisSite"));return}const e=Array.from(document.querySelectorAll('style, link[rel="stylesheet"]')).map(i=>i.outerHTML).join(`
`),r=lt.value.innerHTML;t.document.open(),t.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>${o.value.contract_number} - ${o.value.contract||o.value.contract_template?.name||s("pages.contracts.allContracts.allContracts.untitledContract")}</title>
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
          ${r}
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
  `),t.document.close(),U.value=!1},At=()=>{W.value="add",q.value={id:0,contract_number:"",contract:"",status:"active",start_date:new Date().toISOString().slice(0,10),counterparty:"",is_cancelled:!1,template_id:O.value[0]?.id||0,company_id:null},L.value=!0},Kt=t=>{W.value="edit",q.value={...t},L.value=!0},St=t=>t.contract||t.contract_template?.name||s("pages.contracts.allContracts.allContracts.unnamedContract"),Tt=(t,e)=>{_.value={type:t,contract:e},j.value=!0},Qt=async()=>{const t=_.value;if(t){j.value=!1;try{t.type==="delete"?(await zt(t.contract.id),k.success(s("pages.contracts.allContracts.allContracts.contractDeleted"))):(rt.value=!0,await ft.cancel(t.contract.id),await at(ct.value),k.success(s("pages.contracts.allContracts.allContracts.contractCancelled")))}catch(e){console.error(`Failed to ${t.type} contract:`,e),k.error(s(t.type==="delete"?"pages.contracts.allContracts.allContracts.failedToDeleteContractPleaseTryAgain":"pages.contracts.allContracts.allContracts.failedToCancelContractPleaseTryAgain"))}finally{rt.value=!1,_.value=null}}},Xt=t=>{Tt("cancel",t)},Gt=t=>{Tt("delete",t)},Jt=async t=>{try{W.value==="add"?await Ut(t):q.value&&await Rt(q.value.id,t),L.value=!1,k.success(s("pages.contracts.allContracts.allContracts.contractSaved"))}catch{k.error(s("pages.contracts.allContracts.allContracts.failedToSaveContractPleaseTryAgain"))}};return le(async()=>{await Promise.all([at(ct.value),Lt()]),O.value=O.value.filter(r=>D.contractAudience==="employees"?r.type_slug==="employment":D.contractAudience==="customers"?r.type_slug==="customer":r.type_slug!=="employment"&&r.type_slug!=="customer");const t=Number(f.query.employeeId);if(D.contractAudience==="employees"&&f.query.print==="1"&&t){const r=z.value.find(i=>i.employee_id===t&&!i.is_cancelled);r?await dt(r):k.warning(s("pages.contracts.allContracts.allContracts.noActiveEmploymentContractFound"))}f.query.action==="create"&&(Number(f.query.companyId)||qt.value)&&(At(),await et.replace({name:ht()}));const e=Number(f.query.contractId);if(f.query.action==="print"&&e){const r=z.value.find(i=>i.id===e)??await ft.getById(e);await dt(r),await et.replace({name:ht()})}else if(e){const r=z.value.find(i=>i.id===e);r&&(P.value=r.contract_number||r.counterparty||r.contract)}try{const r=await $e.getAll();$t.value=r.data.filter(i=>i.status==="active").map(i=>({title:i.name||i.type,value:i.id}))}catch{}try{m.value=(await _e.getCompanyProfile()).data}catch{}}),oe([K,st],()=>at(ct.value)),(t,e)=>{const r=pe,i=me,x=de,mt=se;return v(),E(Ct,null,[n(r,{items:ut.value,title:Bt.value,description:Ft.value,icon:"tabler-file-description"},null,8,["items","title","description"]),n(Z,null,{default:l(()=>[n(J,null,{default:l(()=>[n(Se,{align:"center",class:"table-toolbar"},{default:l(()=>[n(yt,{cols:"12",md:"4",lg:"6"},{default:l(()=>[n(re,{modelValue:P.value,"onUpdate:modelValue":e[0]||(e[0]=a=>P.value=a),class:"data-table-search","prepend-inner-icon":"tabler-search",placeholder:t.$t("pages.contracts.allContracts.allContracts.searchContracts"),density:"compact",clearable:"","hide-details":""},null,8,["modelValue","placeholder"])]),_:1}),tt.contractAudience==="customers"?(v(),h(yt,{key:0,cols:"12",md:"3"},{default:l(()=>[n(pt,{modelValue:wt.value,"onUpdate:modelValue":e[1]||(e[1]=a=>wt.value=a),items:$t.value,label:t.$t("common.navigation.services"),density:"compact",clearable:"","hide-details":""},null,8,["modelValue","items","label"])]),_:1})):b("",!0),n(yt,{cols:"12",md:"5",lg:"3",class:"table-toolbar-actions d-flex flex-nowrap justify-end gap-2 ms-auto"},{default:l(()=>[n(i,{active:!!(N.value||I.value),onReset:e[4]||(e[4]=a=>{N.value=null,I.value=null})},{default:l(()=>[n(pt,{modelValue:N.value,"onUpdate:modelValue":e[2]||(e[2]=a=>N.value=a),items:Ht.value,label:t.$t("pages.contracts.allContracts.allContracts.filterByCounterparty"),density:"compact",clearable:"","hide-details":""},null,8,["modelValue","items","label"]),n(pt,{modelValue:I.value,"onUpdate:modelValue":e[3]||(e[3]=a=>I.value=a),items:jt,label:t.$t("common.messages.filterByStatus"),density:"compact",clearable:"","hide-details":""},null,8,["modelValue","label"])]),_:1},8,["active"]),n(w,{icon:"tabler-printer",variant:"text",class:"flex-shrink-0",onClick:Wt},{default:l(()=>[n(S,{icon:"tabler-printer"}),n(M,{activator:"parent",location:"top"},{default:l(()=>[d(c(t.$t("pages.contracts.allContracts.allContracts.printTable")),1)]),_:1})]),_:1}),n(w,{color:"primary",class:"flex-shrink-0",loading:$(bt),onClick:At},{default:l(()=>[n(S,{icon:"tabler-plus",class:"me-1"}),d(" "+c(t.$t("pages.contracts.allContracts.allContracts.addContract")),1)]),_:1},8,["loading"])]),_:1})]),_:1})]),_:1}),$(_t)?(v(),h(Te,{key:0,type:"error",class:"ma-4"},{default:l(()=>[d(c($(_t)),1)]),_:1})):b("",!0),n(mt,{headers:kt.value,items:ut.value,"item-value":"id",class:"text-no-wrap","mobile-breakpoint":"sm",loading:$(bt),"loading-text":t.$t("pages.contracts.allContracts.allContracts.loadingContracts"),style:{"overflow-x":"auto"}},{"item.contract":l(({item:a})=>[d(c(a.contract||a.contract_template?.name||t.$t("pages.contracts.allContracts.allContracts.untitledContract"))+" ",1),a.is_cancelled?(v(),h(Dt,{key:0,color:"error",size:"x-small",class:"ms-2"},{default:l(()=>[d(c(t.$t("common.statuses.cancelled")),1)]),_:1})):b("",!0)]),"item.counterparty":l(({item:a})=>[d(c(a.counterparty||t.$t("pages.contracts.allContracts.allContracts.unknownCounterparty")),1)]),"item.service":l(({item:a})=>[d(c(a.services?.map(y=>y.service?.name||y.service?.type).filter(Boolean).join(", ")||"—"),1)]),"item.start_date":l(({item:a})=>[d(c(("formatLocalizedDate"in t?t.formatLocalizedDate:$(Pt))(a.start_date)),1)]),"item.status":l(({item:a})=>[n(Dt,{color:Mt(a.status),size:"small",class:"font-weight-medium"},{default:l(()=>[d(c(("translateBackendValue"in t?t.translateBackendValue:$(ce))(a.status||"unknown")),1)]),_:2},1032,["color"])]),"item.actions":l(({item:a})=>[n(x,{item:a,title:a.contract_number||a.contract},null,8,["item","title"]),!a.is_cancelled&&["draft","active"].includes(a.status)?(v(),h(w,{key:0,icon:"tabler-ban",variant:"text",size:"small",loading:rt.value,class:"me-2",color:"error",onClick:y=>Xt(a)},{default:l(()=>[n(S,{icon:"tabler-ban"}),n(M,{activator:"parent",location:"top"},{default:l(()=>[d(c(t.$t("pages.contracts.allContracts.allContracts.cancelContract")),1)]),_:1})]),_:1},8,["loading","onClick"])):b("",!0),a.status==="draft"&&!a.is_cancelled?(v(),h(w,{key:1,icon:"tabler-edit",variant:"text",size:"small",class:"me-2",onClick:y=>Kt(a)},{default:l(()=>[n(S,{icon:"tabler-edit"}),n(M,{activator:"parent",location:"top"},{default:l(()=>[d(c(t.$t("pages.contracts.allContracts.allContracts.editContract")),1)]),_:1})]),_:1},8,["onClick"])):b("",!0),a.status==="draft"&&!a.is_cancelled?(v(),h(w,{key:2,icon:"tabler-trash",variant:"text",size:"small",class:"me-2",onClick:y=>Gt(a)},{default:l(()=>[n(S,{icon:"tabler-trash"}),n(M,{activator:"parent",location:"top"},{default:l(()=>[d(c(t.$t("pages.contracts.allContracts.allContracts.deleteContract")),1)]),_:1})]),_:1},8,["onClick"])):b("",!0),n(w,{icon:"tabler-printer",variant:"text",size:"small",loading:ot.value,onClick:y=>dt(a)},{default:l(()=>[n(S,{icon:"tabler-printer"}),n(M,{activator:"parent",location:"top"},{default:l(()=>[d(c(t.$t("pages.contracts.allContracts.allContracts.printContract")),1)]),_:1})]),_:1},8,["loading","onClick"])]),_:1},8,["headers","items","loading","loading-text"])]),_:1}),n(we,{title:$(g)(it.value.report),subtitle:t.$t("pages.contracts.allContracts.allContracts.filteredContractRecords"),columns:kt.value.slice(0,-1).map(a=>a.title),rows:ut.value.map(Ot)},null,8,["title","subtitle","columns","rows"]),n(gt,{modelValue:L.value,"onUpdate:modelValue":e[6]||(e[6]=a=>L.value=a),"max-width":"600px",persistent:""},{default:l(()=>[n(Z,null,{default:l(()=>[n(vt,null,{default:l(()=>[T("span",De,c(W.value==="add"?t.$t("pages.contracts.allContracts.allContracts.addNewContract"):t.$t("pages.contracts.allContracts.allContracts.editContract")),1)]),_:1}),n(J,null,{default:l(()=>[n(fe,{contract:q.value||void 0,templates:$(O),audience:tt.contractAudience,onSubmit:Jt,onCancel:e[5]||(e[5]=a=>L.value=!1)},null,8,["contract","templates","audience"])]),_:1})]),_:1})]),_:1},8,["modelValue"]),n(gt,{modelValue:j.value,"onUpdate:modelValue":e[8]||(e[8]=a=>j.value=a),"max-width":"460px"},{default:l(()=>[n(Z,null,{default:l(()=>[n(vt,null,{default:l(()=>[d(c(t.$t("pages.contracts.allContracts.allContracts.confirmContractAction")),1)]),_:1}),n(J,null,{default:l(()=>[d(c(_.value?.type==="delete"?t.$t("pages.contracts.allContracts.allContracts.deleteContractConfirmation",{contract:_.value?St(_.value.contract):""}):t.$t("pages.contracts.allContracts.allContracts.cancelContractConfirmation",{contract:_.value?St(_.value.contract):""})),1)]),_:1}),n(Nt,null,{default:l(()=>[n(ie),n(w,{variant:"tonal",color:"secondary",onClick:e[7]||(e[7]=a=>j.value=!1)},{default:l(()=>[d(c(t.$t("common.actions.cancel")),1)]),_:1}),n(w,{color:_.value?.type==="delete"?"error":"warning",onClick:Qt},{default:l(()=>[d(c(_.value?.type==="delete"?t.$t("common.actions.delete"):t.$t("pages.contracts.allContracts.allContracts.cancelContract")),1)]),_:1},8,["color"])]),_:1})]),_:1})]),_:1},8,["modelValue"]),n(gt,{modelValue:U.value,"onUpdate:modelValue":e[16]||(e[16]=a=>U.value=a),"max-width":"480px"},{default:l(()=>[n(Z,null,{default:l(()=>[n(vt,{class:"d-flex align-center gap-2"},{default:l(()=>[n(S,{icon:"tabler-printer"}),T("span",null,c(t.$t("pages.contracts.allContracts.allContracts.printContractOptions")),1)]),_:1}),n(J,null,{default:l(()=>[T("p",Pe,[d(c(t.$t("pages.contracts.allContracts.allContracts.prepare"))+" ",1),T("strong",null,c(o.value?.contract||o.value?.contract_template?.name||t.$t("pages.contracts.allContracts.allContracts.untitledContract")),1),d(" "+c(t.$t("pages.contracts.allContracts.allContracts.forA4PrintingTheDocumentWillIncludeTheCoverPageAndAllContractTerms")),1)]),T("div",Ne,[n(R,{modelValue:F.value,"onUpdate:modelValue":e[9]||(e[9]=a=>F.value=a),label:t.$t("pages.contracts.allContracts.allContracts.includeFormalSignatureStampSectionAtTheEnd"),density:"compact","hide-details":""},null,8,["modelValue","label"]),n(R,{modelValue:nt.value,"onUpdate:modelValue":e[10]||(e[10]=a=>nt.value=a),label:t.$t("pages.contracts.allContracts.allContracts.useCustomCoverPageFromSettings"),density:"compact","hide-details":""},null,8,["modelValue","label"]),n(R,{modelValue:Y.value,"onUpdate:modelValue":e[11]||(e[11]=a=>Y.value=a),label:t.$t("pages.contracts.allContracts.allContracts.useCustomLetterheadFromSettings"),density:"compact","hide-details":""},null,8,["modelValue","label"]),X.value?(v(),h(R,{key:0,modelValue:V.value,"onUpdate:modelValue":e[12]||(e[12]=a=>V.value=a),label:t.$t("pages.contracts.shared.includePartyDetails"),density:"compact","hide-details":""},null,8,["modelValue","label"])):b("",!0),xt.value?(v(),h(R,{key:1,modelValue:H.value,"onUpdate:modelValue":e[13]||(e[13]=a=>H.value=a),label:t.$t("pages.contracts.shared.header.includeContractHeader"),hint:t.$t("pages.contracts.shared.header.includeContractHeaderHint"),density:"compact","hide-details":"","persistent-hint":""},null,8,["modelValue","label","hint"])):b("",!0),n(R,{modelValue:B.value,"onUpdate:modelValue":e[14]||(e[14]=a=>B.value=a),label:t.$t("pages.contracts.allContracts.allContracts.addALastFullyEmptyPage"),density:"compact","hide-details":""},null,8,["modelValue","label"])])]),_:1}),n(Nt,{class:"justify-end px-4 pb-4"},{default:l(()=>[n(w,{variant:"outlined",color:"secondary",onClick:e[15]||(e[15]=a=>U.value=!1)},{default:l(()=>[d(c(t.$t("common.actions.cancel")),1)]),_:1}),n(w,{color:"primary",onClick:Yt},{default:l(()=>[d(c(t.$t("pages.contracts.allContracts.allContracts.printA4Document")),1)]),_:1})]),_:1})]),_:1})]),_:1},8,["modelValue"]),T("div",{ref_key:"combinedPrintRef",ref:lt,style:{display:"none"}},[o.value?(v(),E(Ct,{key:0},[nt.value&&(A.value?.cover_design_url||m.value?.contract_cover_design_url)?(v(),E("div",Ie,[T("img",{src:A.value?.cover_design_url||m.value.contract_cover_design_url,alt:""},null,8,Le)])):(v(),h(ye,{key:1,"contractee-name":o.value.counterparty||"",logo:C.value.logo,"cover-design-url":"","contract-number":o.value.contract_number||"","contract-title":Vt.value,"cover-description":o.value.cover_text||o.value.contract_template?.cover_text||"","company-website":C.value.website||"","company-address":C.value.address||"","company-email":C.value.email||"","company-number":C.value.phone||""},null,8,["contractee-name","logo","contract-number","contract-title","cover-description","company-website","company-address","company-email","company-number"])),V.value&&X.value?(v(),h(It,{key:2,"document-title":t.$t("pages.contracts.shared.contractParties"),"document-number":o.value.contract_number||"",company:{...C.value,letterheadUrl:Y.value?C.value.letterheadUrl:void 0},"party-details":$(he)(o.value,C.value),"contractee-name":o.value.counterparty||"","page-number-string":t.$t("pages.contracts.allContracts.allContracts.pageNumber",{page:1,total:G(o.value).length+1}),"footer-text":t.$t("pages.contracts.allContracts.allContracts.confidentialAllRightsReserved"),class:"contract-content"},null,8,["document-title","document-number","company","party-details","contractee-name","page-number-string","footer-text"])):b("",!0),(v(!0),E(Ct,null,ue(G(o.value),(a,y)=>(v(),h(It,{key:y,"document-number":o.value.contract_number||"","document-title":Vt.value,company:{...C.value,letterheadUrl:Y.value?C.value.letterheadUrl:void 0},"header-details":H.value&&y===0?Et.value:null,"html-content":a,"page-number-string":t.$t("pages.contracts.allContracts.allContracts.pageNumber",{page:y+(V.value&&X.value?2:1),total:G(o.value).length+(V.value&&X.value?1:0)}),"show-signature":F.value&&y===G(o.value).length-1,"contractee-name":o.value.counterparty||"","company-signatory-name":o.value.company_signatory_name||null,"footer-text":t.$t("pages.contracts.allContracts.allContracts.confidentialAllRightsReserved"),class:"contract-content"},null,8,["document-number","document-title","company","header-details","html-content","page-number-string","show-signature","contractee-name","company-signatory-name","footer-text"]))),128)),B.value?(v(),E("div",Ue)):b("",!0)],64)):b("",!0)],512)],64)}}});export{Xe as _};
