import{useEffect as l,useRef as p}from"react";import{createPortal as u}from"react-dom";import{createRoot as f}from"react-dom/client";import{useEffect as C}from"react";var e=(...i)=>{let t=new Set;return i.forEach(n=>{n&&n.split(/\s+/).filter(Boolean).forEach(s=>{t.add(s)})}),Array.from(t).join(" ")};import{Fragment as v,jsx as o,jsxs as m}from"react/jsx-runtime";function b({title:i,onClose:t,children:n,footer:s,size:c="md",closeLabel:a="Close"}){return l(()=>{document.body.classList.add("mc-modal-open");let r=d=>{d.key==="Escape"&&t()};return document.addEventListener("keydown",r),()=>{document.body.classList.remove("mc-modal-open"),document.removeEventListener("keydown",r)}},[t]),u(o("div",{className:e("mc-modal-backdrop"),onClick:t,children:m("div",{className:e("mc-modal",`mc-modal--${c}`),role:"dialog","aria-modal":"true",onClick:r=>r.stopPropagation(),children:[m("div",{className:e("mc-modal__header"),children:[i?o("h2",{className:e("mc-modal__title"),children:i}):o("span",{}),o("button",{type:"button",className:e("mc-button mc-modal__close"),onClick:t,"aria-label":a,children:o("i",{className:"bi bi-x-lg","aria-hidden":"true"})})]}),o("div",{className:e("mc-modal__body"),children:n}),s&&o("div",{className:e("mc-modal__footer"),children:s})]})}),document.body)}function g({title:i,message:t,confirmLabel:n="Confirm",cancelLabel:s="Cancel",danger:c=!1,onResolve:a}){let r=p(null);l(()=>{r.current?.focus()},[]);let d=m(v,{children:[o("button",{ref:r,type:"button",className:e(c?"mc-button btn-mc-danger":"mc-button btn-mc-primary"),onClick:()=>a(!0),children:n}),o("button",{type:"button",className:e("mc-button btn-mc-secondary"),onClick:()=>a(!1),children:s})]});return o(b,{title:i,onClose:()=>a(!1),size:"sm",footer:d,closeLabel:s,children:m("div",{className:e("mc-modal__confirm"),children:[c&&o("span",{className:e("mc-modal__icon mc-modal__icon--danger"),"aria-hidden":"true",children:o("i",{className:"bi bi-exclamation-triangle"})}),o("p",{className:e("mc-modal__message"),children:t})]})})}function O(i){return new Promise(t=>{let n=document.createElement("div");document.body.appendChild(n);let s=f(n),c=a=>{s.unmount(),n.remove(),t(a)};s.render(o(g,{...i,onResolve:c}))})}export{b as Modal,O as confirmDialog};
/**
 * Runtime design-system helpers for Modern Commerce React surfaces.
 *
 * @module     local_moderncommerce/design_system
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
