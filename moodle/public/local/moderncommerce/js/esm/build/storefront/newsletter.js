import{useState as E}from"react";import{useEffect as U}from"react";var N=(...e)=>{let n=new Set;return e.forEach(t=>{t&&t.split(/\s+/).filter(Boolean).forEach(o=>{n.add(o)})}),Array.from(n).join(" ")};import{jsx as P}from"react/jsx-runtime";function S({variant:e,size:n,loading:t=!1,loadingLabel:o,buttonState:s,block:i=!1,className:r,children:d,disabled:w,type:f="button","aria-busy":l,...p}){let a=t||l===!0||l==="true";return P("button",{...p,"aria-busy":a?"true":l,className:N("mc-button",i&&"mc-button--block",r),"data-mc-button":e,"data-mc-button-size":n,"data-mc-button-state":a?"loading":s,disabled:w||t,type:f,children:t&&o!==void 0?o:d})}var C=/^[^@\s]+@[^@\s]+\.[^@\s]+$/;import{useEffect as v,useRef as F,useState as q}from"react";import{jsx as L,jsxs as I}from"react/jsx-runtime";var A="local-moderncommerce-recaptcha-api",B="localModernCommerceRecaptchaLoaded",_=()=>({enabled:!1,sitekey:"",apiurl:"",lang:"",requiredmessage:"",errormessage:""}),H=(e,n)=>window.grecaptcha?.render?Promise.resolve():(window.localModernCommerceRecaptchaPromise||(window.localModernCommerceRecaptchaPromise=new Promise((t,o)=>{window.localModernCommerceRecaptchaLoaded=()=>t();let s=document.getElementById(A);if(s){s.addEventListener("load",()=>t(),{once:!0}),s.addEventListener("error",()=>o(new Error("reCAPTCHA failed to load.")),{once:!0});return}let i=new URL(e,window.location.href);i.searchParams.set("onload",B),i.searchParams.set("render","explicit"),n&&i.searchParams.set("hl",n);let r=document.createElement("script");r.id=A,r.src=i.toString(),r.async=!0,r.defer=!0,r.onerror=()=>o(new Error("reCAPTCHA failed to load.")),document.head.appendChild(r)})),window.localModernCommerceRecaptchaPromise),O=e=>{let n=new FormData(e).get("g-recaptcha-response");return typeof n=="string"?n.trim():""},T=e=>{e.querySelectorAll("[data-mc-recaptcha-widget-id]").forEach(n=>{let t=Number(n.dataset.mcRecaptchaWidgetId);Number.isFinite(t)&&window.grecaptcha?.reset(t)})};function h({config:e,className:n=""}){let t=F(null),o=F(null),[s,i]=q("");return v(()=>{if(!e.enabled||!e.sitekey||!e.apiurl||!t.current)return;let r=!1;return i(""),H(e.apiurl,e.lang).then(()=>{r||!t.current||o.current!==null||!window.grecaptcha?.render||(o.current=window.grecaptcha.render(t.current,{sitekey:e.sitekey}),t.current.dataset.mcRecaptchaWidgetId=String(o.current))}).catch(()=>i(e.errormessage)),()=>{r=!0}},[e.enabled,e.sitekey,e.apiurl,e.lang,e.errormessage]),e.enabled?I("div",{className:n||void 0,children:[L("div",{ref:t}),s&&L("p",{className:"mc-recaptcha-error",children:s})]}):null}import{jsx as c,jsxs as x}from"react/jsx-runtime";var D=async(e,n,t)=>{let o=new URLSearchParams({sesskey:M.cfg.sesskey,info:e}),s=await fetch(`${M.cfg.wwwroot}/lib/ajax/service.php?${o.toString()}`,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify([{index:0,methodname:e,args:n}])});if(!s.ok)throw new Error(`${s.status} ${s.statusText}`);let i=await s.json(),r=Array.isArray(i)?i[0]:i;if(!r)throw new Error("Empty Moodle service response.");if(r.error)throw new Error(r.exception?.message??t);return r.data??r};function $({method:e,heading:n,description:t,placeholder:o,buttonlabel:s,successmessage:i,recaptcha:r=_(),labels:d,style:w}){let[f,l]=E(""),[p,a]=E("idle"),[R,u]=E("");return c("section",{className:"mw-news",style:w,children:x("div",{className:"mw-news__inner",children:[n&&c("h2",{className:"mw-news__heading",children:n}),t&&c("p",{className:"mw-news__desc",children:t}),p==="done"?x("p",{className:"mw-news__success",children:[c("i",{className:"bi bi-check-circle-fill","aria-hidden":"true"})," ",R]}):x("form",{className:"mw-news__form",onSubmit:async g=>{g.preventDefault();let b=g.currentTarget,y=f.trim();if(!y){a("error"),u(d.emailrequired);return}if(!C.test(y)){a("error"),u(d.invalidemail);return}let k=r.enabled?O(b):"";if(r.enabled&&!k){a("error"),u(r.requiredmessage);return}a("busy"),u("");try{let m=await D(e,{email:y,source:"storefront",recaptcharesponse:k},d.servicerequestfailed);m.success?(a("done"),u(m.message||i),l("")):(a("error"),u(m.message),T(b))}catch(m){a("error"),u(m.message),T(b)}},children:[c("input",{type:"email",className:"mw-news__input",placeholder:o,"aria-label":o,value:f,onChange:g=>l(g.currentTarget.value)}),c(h,{config:r,className:"mw-news__captcha"}),c(S,{type:"submit",className:"mw-news__btn",variant:"soft",loading:p==="busy",loadingLabel:d.subscribing,children:s})]}),p==="error"&&R&&c("p",{className:"mw-news__error",children:R})]})})}export{$ as default};
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
 * Framework-free form validation rules for Modern Commerce.
 *
 * Every rule is a plain function returning the error message when the value is
 * invalid, or undefined when it is fine. Messages are always supplied by the
 * caller from its own `labels` prop — this module never contains user-facing
 * English, so it stays translatable.
 *
 * @module     local_moderncommerce/validation
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * React wrapper for Moodle's configured Google reCAPTCHA v2 widget.
 *
 * @module     local_moderncommerce/storefront/recaptcha
 * @copyright  2026 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Newsletter / lead-capture widget for the Modern Commerce storefront.
 *
 * @module     local_moderncommerce/storefront/newsletter
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
