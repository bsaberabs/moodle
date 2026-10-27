import{useEffect as ke,useState as w}from"react";import{useEffect as fe}from"react";var be=()=>{},s=(...e)=>{let a=new Set;return e.forEach(r=>{r&&r.split(/\s+/).filter(Boolean).forEach(l=>{a.add(l)})}),Array.from(a).join(" ")};var X=()=>{fe(()=>{be()},[])},K=e=>{let a=window.require;typeof a=="function"&&a(["local_moderncommerce/floating_notifications"],r=>e(r))},v={success:(e,a)=>K(r=>r.success(e,a)),error:(e,a)=>K(r=>r.error(e,a)),warning:(e,a)=>K(r=>r.warning(e,a)),info:(e,a)=>K(r=>r.info(e,a))};import{jsx as ye}from"react/jsx-runtime";function Z({variant:e,size:a,loading:r=!1,loadingLabel:l,buttonState:d,block:c=!1,className:t,children:i,disabled:N,type:C="button","aria-busy":y,..._}){let $=r||y===!0||y==="true";return ye("button",{..._,"aria-busy":$?"true":y,className:s("mc-button",c&&"mc-button--block",t),"data-mc-button":e,"data-mc-button-size":a,"data-mc-button-state":$?"loading":d,disabled:N||r,type:C,children:r&&l!==void 0?l:i})}import{useEffect as ee,useRef as ve}from"react";import{createPortal as xe}from"react-dom";import{createRoot as we}from"react-dom/client";import{Fragment as Ce,jsx as g,jsxs as U}from"react/jsx-runtime";function Se({title:e,onClose:a,children:r,footer:l,size:d="md",closeLabel:c="Close"}){return ee(()=>{document.body.classList.add("mc-modal-open");let t=i=>{i.key==="Escape"&&a()};return document.addEventListener("keydown",t),()=>{document.body.classList.remove("mc-modal-open"),document.removeEventListener("keydown",t)}},[a]),xe(g("div",{className:s("mc-modal-backdrop"),onClick:a,children:U("div",{className:s("mc-modal",`mc-modal--${d}`),role:"dialog","aria-modal":"true",onClick:t=>t.stopPropagation(),children:[U("div",{className:s("mc-modal__header"),children:[e?g("h2",{className:s("mc-modal__title"),children:e}):g("span",{}),g("button",{type:"button",className:s("mc-button mc-modal__close"),onClick:a,"aria-label":c,children:g("i",{className:"bi bi-x-lg","aria-hidden":"true"})})]}),g("div",{className:s("mc-modal__body"),children:r}),l&&g("div",{className:s("mc-modal__footer"),children:l})]})}),document.body)}function Ne({title:e,message:a,confirmLabel:r="Confirm",cancelLabel:l="Cancel",danger:d=!1,onResolve:c}){let t=ve(null);ee(()=>{t.current?.focus()},[]);let i=U(Ce,{children:[g("button",{ref:t,type:"button",className:s(d?"mc-button btn-mc-danger":"mc-button btn-mc-primary"),onClick:()=>c(!0),children:r}),g("button",{type:"button",className:s("mc-button btn-mc-secondary"),onClick:()=>c(!1),children:l})]});return g(Se,{title:e,onClose:()=>c(!1),size:"sm",footer:i,closeLabel:l,children:U("div",{className:s("mc-modal__confirm"),children:[d&&g("span",{className:s("mc-modal__icon mc-modal__icon--danger"),"aria-hidden":"true",children:g("i",{className:"bi bi-exclamation-triangle"})}),g("p",{className:s("mc-modal__message"),children:a})]})})}function te(e){return new Promise(a=>{let r=document.createElement("div");document.body.appendChild(r);let l=we(r),d=c=>{l.unmount(),r.remove(),a(c)};l.render(g(Ne,{...e,onResolve:d}))})}import{Fragment as se,jsx as n,jsxs as m}from"react/jsx-runtime";var A="{content_html}",J="{unsubscribe_html}",O="#7c3aed",j="#1e1b4b",Te="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='168' height='48' viewBox='0 0 168 48'%3E%3Crect width='168' height='48' rx='8' fill='%23ffffff'/%3E%3Ccircle cx='26' cy='24' r='12' fill='%230f766e'/%3E%3Cpath d='M21 25.5l3.2 3.2L31.5 19' fill='none' stroke='%23ffffff' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Ctext x='48' y='30' font-family='Arial,sans-serif' font-size='16' font-weight='700' fill='%230f172a'%3ELogo%3C/text%3E%3C/svg%3E",Y=`<h2 style="margin:0 0 12px; font-size:24px; line-height:1.25;">Advance your team with a new programme</h2>
<p style="margin:0 0 16px;">Hello Jane, your course package is ready. Secure your place today and start learning with guided modules, practical resources, and certificate-ready outcomes.</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0;">
    <tr>
        <td><a class="mc-button mc-email-button" data-mc-button="primary" href="{siteurl}">Explore programme</a></td>
    </tr>
</table>
<p style="margin:0;">Featured programme: Digital Commerce Leadership</p>`,F=async(e,a)=>{let r=`${M.cfg.wwwroot}/lib/ajax/service.php?sesskey=${encodeURIComponent(M.cfg.sesskey)}&info=${encodeURIComponent(e)}`,l=await fetch(r,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify([{index:0,methodname:e,args:a}])});if(!l.ok)throw new Error(`${l.status} ${l.statusText}`);let d=await l.json(),c=Array.isArray(d)?d[0]:d;if(!c)throw new Error("Empty Moodle service response.");if(c.error){let t=c.exception??{};throw new Error(t.message??c.message??"Moodle service request failed.")}return c.data??c},I=e=>e instanceof Error?e.message:String(e),b=(e,a)=>{let r=e.trim();return/^#[0-9a-f]{6}$/i.test(r)?r:a},oe=(e,a)=>({logo:"{logo}",sitename:"{sitename}",siteurl:"{siteurl}",supportemail:"{supportemail}",supporturl:"{siteurl}",emailbg:"#f4f7fb",headerbg:b(a,j),contentbg:"#ffffff",footerbg:"#edf2f7",primarycolor:b(e,O),textcolor:"#172033",containerwidth:"640",buttonradius:"6",headerstyle:"split",footertext:"You are receiving this email because you purchased, enrolled in, or showed interest in a course or programme.",showsupport:!0,showunsubscribe:!0}),u=e=>e.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;"),re=(e,a,r,l)=>{let d=Number.parseInt(e,10);return Number.isNaN(d)?l:Math.min(r,Math.max(a,d))},T=(e,a,r)=>e.split(a).join(r),_e=(e,a,r)=>{let l=e.logo.trim()||"{logo}",d=e.sitename.trim()||"{sitename}",c=e.siteurl.trim()||"{siteurl}",t=`<img src="${u(l)}" width="152" alt="${u(d)}" style="display:block; max-width:152px; width:152px; height:auto;">`,i=`<a href="${u(c)}" style="color:#ffffff; font-size:18px; font-weight:700; line-height:1.2; text-decoration:none;">${u(d)}</a>`;return e.headerstyle==="centered"?`<tr>
    <td align="center" style="background:${a}; padding:30px 28px;">
        ${t}
        <div style="height:12px; line-height:12px;">&nbsp;</div>
        ${i}
    </td>
</tr>`:e.headerstyle==="compact"?`<tr>
    <td style="background:${a}; padding:22px 28px;">
        <div style="color:#ffffff; font-size:20px; font-weight:800; line-height:1.25;">${u(d)}</div>
        <div style="height:4px; line-height:4px;">&nbsp;</div>
        <a href="${u(c)}" style="color:#d7f8f2; font-size:13px; text-decoration:none;">${u(c)}</a>
    </td>
</tr>`:`<tr>
    <td style="background:${a}; border-bottom:4px solid ${r}; padding:24px 28px;">
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
            <tr>
                <td align="left" style="vertical-align:middle;">${t}</td>
                <td align="right" style="vertical-align:middle;">${i}</td>
            </tr>
        </table>
    </td>
</tr>`},$e=e=>{let a=re(e.containerwidth,480,760,640),r=re(e.buttonradius,0,24,6),l=b(e.emailbg,"#f4f7fb"),d=b(e.headerbg,j),c=b(e.contentbg,"#ffffff"),t=b(e.footerbg,"#edf2f7"),i=b(e.primarycolor,O),N=b(e.textcolor,"#172033"),C=e.supportemail.trim()||"{supportemail}",y=e.supporturl.trim()||e.siteurl.trim()||"{siteurl}",_=e.showsupport?`<p style="margin:8px 0 0; font-size:13px; line-height:1.5;">
            <a href="${u(y)}" style="color:${i}; text-decoration:none;">Support</a>
            <span style="color:#94a3b8;"> | </span>
            <a href="mailto:${u(C)}" style="color:${i}; text-decoration:none;">${u(C)}</a>
        </p>`:"",$=e.showunsubscribe?`<div style="margin-top:14px; font-size:12px; line-height:1.5; color:#64748b;">${J}</div>`:"";return`<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>${u(e.sitename.trim()||"{sitename}")}</title>
    <style>
        body, table, td, a { -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
        table, td { mso-table-lspace:0pt; mso-table-rspace:0pt; }
        img { -ms-interpolation-mode:bicubic; border:0; height:auto; line-height:100%; outline:none; text-decoration:none; }
        a { color:${i}; }
        .mc-email-button { background:${i}; border-radius:${r}px; color:#ffffff !important; display:inline-block; font-weight:700; padding:12px 18px; text-decoration:none; }
        @media screen and (max-width: 640px) {
            .mc-email-container { width:100% !important; max-width:100% !important; }
            .mc-email-padding { padding-left:20px !important; padding-right:20px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:${l}; color:${N}; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:${l}; border-collapse:collapse; margin:0; padding:0;">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" cellpadding="0" cellspacing="0" width="${a}" class="mc-email-container" style="border-collapse:collapse; max-width:${a}px; width:${a}px;">
                    ${_e(e,d,i)}
                    <tr>
                        <td class="mc-email-padding" style="background:${c}; color:${N}; font-size:16px; line-height:1.6; padding:32px;">
                            ${A}
                        </td>
                    </tr>
                    <tr>
                        <td class="mc-email-padding" style="background:${t}; color:#64748b; font-size:13px; line-height:1.5; padding:22px 32px;">
                            <p style="margin:0;">${u(e.footertext.trim())}</p>
                            ${_}
                            ${$}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>`},Ee=(e,a,r)=>{let l=r.sitename.replace(/[{}]/g,"").trim()||"Modern Commerce",d=r.siteurl.includes("{")?"#":r.siteurl,c=r.supportemail.includes("{")?"support@example.com":r.supportemail,t=r.logo.includes("{")?Te:r.logo,i=e;return i=T(i,A,a.trim()||Y),i=T(i,J,`<a href="#" style="color:${b(r.primarycolor,O)}; text-decoration:none;">Manage email preferences</a>`),i=T(i,"{sitename}",u(l)),i=T(i,"{siteurl}",u(d||"#")),i=T(i,"{supportemail}",u(c||"support@example.com")),i=T(i,"{logo}",u(t)),i=T(i,"{logo_compact}",u(t)),i};function Me({getShellMethodName:e,saveShellMethodName:a,previewShellMethodName:r,resetShellMethodName:l,brandPrimary:d=O,brandSecondary:c=j,labels:t}){X();let[i,N]=w(""),[C,y]=w(""),[_,$]=w(""),[ae,E]=w(""),[V,ne]=w(Y),[S,G]=w(()=>oe(d,c)),[R,z]=w("design"),[h,W]=w(!0),[f,L]=w(!1),ie=$e(S),H=R==="advanced"?C:ie,D=H.includes(A),le=Ee(H,V,S);ke(()=>{let o=!1;return W(!0),F(e,{}).then(p=>{o||(y(p.shell),$(p.defaultshell),p.shell!==""&&p.defaultshell!==""&&p.shell!==p.defaultshell&&z("advanced"))}).catch(p=>{o||v.error(I(p))}).finally(()=>{o||W(!1)}),()=>{o=!0}},[e]);let ce=o=>{let p=navigator.clipboard?.writeText(o);p&&p.then(()=>{N(o),window.setTimeout(()=>N(""),1500)})},B=o=>{G(p=>({...p,...o})),E("")},de=o=>{y(o),E("")},me=async()=>{if(!D){v.error(t.requiredtokenmissing??"The email shell must include {content_html}.");return}L(!0);try{let o=await F(a,{shell:H});if(!o.success){v.error(o.message),y(o.shell);return}y(o.shell),v.success(o.message)}catch(o){v.error(I(o))}finally{L(!1)}},pe=async()=>{if(!D){v.error(t.requiredtokenmissing??"The email shell must include {content_html}.");return}L(!0);try{let o=await F(r,{shell:H,content:V||Y});E(o.body),z("preview")}catch(o){v.error(I(o))}finally{L(!1)}},ue=async()=>{if(await te({message:t.resetconfirm,danger:!0})){L(!0);try{let o=await F(l,{});if(!o.success){v.error(o.message);return}y(o.shell),G(oe(d,c)),z("design"),E(""),v.success(o.message)}catch(o){v.error(I(o))}finally{L(!1)}}},ge={emailbg:"#f4f7fb",headerbg:b(c,j),contentbg:"#ffffff",footerbg:"#edf2f7",primarycolor:b(d,O),textcolor:"#172033"},q=(o,p,x)=>m("button",{className:s(R===o?"mc-button btn-mc-primary":"mc-button mc-btn-soft"),disabled:h||f,onClick:()=>{z(o),E("")},type:"button",children:[n("i",{className:`bi ${p} me-1`,"aria-hidden":"true"}),x]},o),k=(o,p,x="text")=>m("label",{children:[n("span",{className:s("mc-field-label"),children:p}),n("input",{className:s("mc-form-control"),disabled:h||f,onChange:he=>B({[o]:he.target.value}),type:x,value:S[o]})]},o),P=(o,p)=>m("label",{children:[n("span",{className:s("mc-field-label"),children:p}),m("div",{className:s("mc-settings-colorfield"),children:[n("input",{"aria-label":p,disabled:h||f,onChange:x=>B({[o]:x.target.value}),type:"color",value:b(S[o],ge[o])}),n("input",{className:s("mc-form-control"),disabled:h||f,onChange:x=>B({[o]:x.target.value}),placeholder:"#rrggbb",type:"text",value:S[o]})]})]},o),Q=(o,p)=>m("label",{className:s("mc-switch"),children:[n("input",{checked:S[o],disabled:h||f,onChange:x=>B({[o]:x.target.checked}),type:"checkbox"}),n("span",{className:s("mc-switch__track"),"aria-hidden":"true"}),n("span",{className:s("mc-switch__thumb"),"aria-hidden":"true"}),n("span",{className:s("mc-switch__label"),children:p})]},o);return m("section",{className:s("mc-product-admin"),"aria-label":t.shellbuilder??t.shell,children:[m("div",{className:s("mc-card mb-3"),children:[m("div",{className:s("mc-card-header d-flex flex-wrap gap-2 align-items-start justify-content-between"),children:[m("div",{children:[n("h3",{className:s("mc-card-title mb-1"),children:t.shellbuilder??t.shell}),n("p",{className:s("mc-cell-muted small mb-0"),children:t.shellhelp})]}),n("span",{className:s(D?"mc-badge mc-badge--success":"mc-badge mc-badge--danger"),children:A})]}),m("div",{className:s("mc-card-body"),children:[m("div",{className:"d-flex gap-2 flex-wrap mb-3",role:"tablist","aria-label":t.shellbuilder??t.shell,children:[q("design","bi-palette",t.shelldesign??"Design"),q("footer","bi-shield-check",t.shellfooter??"Footer & compliance"),q("preview","bi-window",t.shellpreview??t.preview),q("advanced","bi-code-square",t.shelladvanced??t.shellhtml)]}),!D&&m("div",{className:s("mc-alert mc-alert--danger mb-3"),role:"alert",children:[n("i",{className:"bi bi-exclamation-triangle mc-alert__icon","aria-hidden":"true"}),n("div",{className:s("mc-alert__body"),children:t.requiredtokenmissing??"The email shell must include {content_html}."})]}),R==="design"&&m("div",{className:s("mc-product-form__grid"),children:[k("logo",t.logourl??"Logo URL"),k("sitename",t.brandname??"Brand name"),k("siteurl",t.siteurl??"Site URL"),m("label",{children:[n("span",{className:s("mc-field-label"),children:t.headerstyle??"Header style"}),m("select",{className:s("mc-select"),disabled:h||f,onChange:o=>{B({headerstyle:o.target.value})},value:S.headerstyle,children:[n("option",{value:"split",children:t.headerstylesplit??"Logo left, name right"}),n("option",{value:"centered",children:t.headerstylecentered??"Centered"}),n("option",{value:"compact",children:t.headerstylecompact??"Compact text"})]})]}),P("headerbg",t.headerbg??"Header background"),P("primarycolor",t.primarycolor??"Primary color"),P("emailbg",t.emailbg??"Email background"),P("contentbg",t.contentbg??"Content background"),P("textcolor",t.textcolor??"Text color"),k("containerwidth",t.containerwidth??"Container width","number"),k("buttonradius",t.buttonradius??"Button radius","number")]}),R==="footer"&&m(se,{children:[m("div",{className:s("mc-product-form__grid"),children:[k("supportemail",t.supportemail??"Support email"),k("supporturl",t.supporturl??"Support URL"),P("footerbg",t.footerbg??"Footer background")]}),m("div",{className:"d-flex align-items-center gap-4 flex-wrap mt-3",children:[Q("showsupport",t.showsupport??"Show support link"),Q("showunsubscribe",t.showunsubscribe??"Show unsubscribe")]}),m("label",{className:"d-block mt-3",children:[n("span",{className:s("mc-field-label"),children:t.footertext??"Footer text"}),n("textarea",{className:s("mc-form-control"),disabled:h||f,onChange:o=>B({footertext:o.target.value}),rows:3,value:S.footertext})]})]}),R==="preview"&&m("div",{className:"row g-3",children:[n("div",{className:"col-12 col-xl-5",children:m("label",{className:"d-block",children:[n("span",{className:s("mc-field-label"),children:t.previewcontent}),n("textarea",{className:s("form-control form-control-sm mc-code-textarea"),disabled:h||f,onChange:o=>{ne(o.target.value),E("")},rows:14,value:V})]})}),n("div",{className:"col-12 col-xl-7",children:n("iframe",{className:"mc-email-preview-frame",sandbox:"",srcDoc:ae||le,style:{width:"100%",minHeight:"560px",border:"1px solid #e5e7eb",borderRadius:"6px"},title:t.preview})})]}),R==="advanced"&&m(se,{children:[n("div",{className:"d-flex gap-2 flex-wrap mb-3",children:[A,J,"{sitename}","{siteurl}","{supportemail}","{logo}","{logo_compact}"].map(o=>n("button",{className:s("mc-button mc-badge mc-badge--neutral mc-cell-mono mc-placeholder-chip"),"data-mc-button":"light",onClick:()=>ce(o),type:"button",children:i===o?t.copied:o},o))}),n("textarea",{className:s("form-control form-control-sm mc-code-textarea"),disabled:h||f,onChange:o=>de(o.target.value),rows:26,value:C})]}),m("div",{className:"d-flex gap-2 flex-wrap mt-4",children:[n(Z,{className:s("btn-mc-primary"),disabled:h||!D,loading:f,loadingLabel:t.saving||"Saving...",onClick:me,type:"button",children:t.saveshell}),n("button",{className:s("mc-button mc-btn-soft"),disabled:h||f||!D,onClick:pe,type:"button",children:t.previewshell}),n("button",{className:s("mc-button mc-btn-soft"),disabled:h||f,onClick:ue,type:"button",children:t.resetshell})]})]})]}),_!==""&&n("input",{type:"hidden",value:_,readOnly:!0})]})}export{Me as default};
/**
 * Runtime design-system helpers for Modern Commerce React surfaces.
 *
 * @module     local_moderncommerce/design_system
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Reusable Modern Commerce button primitive for React screens.
 *
 * @module     local_moderncommerce/button
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Reusable modal dialog for Modern Commerce React surfaces.
 *
 * Exposes two things:
 *  - {@link Modal}: a presentational, portalled modal component (header/body/footer)
 *    for declarative use inside any component.
 *  - {@link confirmDialog}: an imperative `Promise<boolean>` confirmation helper that
 *    replaces blocking `window.confirm()` calls. Resolves true on confirm, false on
 *    cancel/dismiss.
 *
 * @module     local_moderncommerce/modal
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * React editor for the global Modern Commerce email shell ("Email branding").
 *
 * The shell wraps every outgoing Modern Commerce email. This editor lives under
 * the Branding admin so the email look-and-feel sits next to the storefront/admin
 * palette. By default it seeds the email primary colour from the brand primary and
 * the header band from the brand secondary, so emails match the configured brand
 * out of the box; admins can still override any colour here.
 *
 * @module     local_moderncommerce/email_branding_admin
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
