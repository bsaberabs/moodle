import{useEffect as g,useId as D,useRef as O}from"react";import{createPortal as E}from"react-dom";import{useEffect as L}from"react";var e=(...s)=>{let o=new Set;return s.forEach(r=>{r&&r.split(/\s+/).filter(Boolean).forEach(m=>{o.add(m)})}),Array.from(o).join(" ")};import{Fragment as S,jsx as t,jsxs as l}from"react/jsx-runtime";var y=1,d=0,a=[],A=()=>(d+=1,document.body.classList.add("mc-drawer-open"),()=>{d=Math.max(0,d-1),d===0&&document.body.classList.remove("mc-drawer-open")}),M=s=>(a.push(s),()=>{let o=a.lastIndexOf(s);o!==-1&&a.splice(o,1)});function V({title:s,subtitle:o,onClose:r,children:m,footer:u,closeLabel:N="Close",ariaLabel:f,className:k,bodyClassName:h,backdropClassName:C,bodyRef:T,nested:b=!1,disableClose:i=!1,closeOnBackdrop:_=!0,closeOnEscape:w=!0}){let v=D(),c=O(0);c.current===0&&(c.current=y,y+=1),g(()=>{let n=A(),p=M(c.current);return()=>{p(),n()}},[]),g(()=>{let n=p=>{let x=a[a.length-1]===c.current;p.key==="Escape"&&w&&!i&&x&&r()};return document.addEventListener("keydown",n),()=>{document.removeEventListener("keydown",n)}},[w,i,r]);let R=()=>{_&&!i&&r()};return E(l(S,{children:[t("div",{className:e("mc-drawer-backdrop",b&&"mc-drawer-backdrop--nested",C),onClick:R,"aria-hidden":"true"}),l("aside",{className:e("mc-drawer mc-drawer--open",b&&"mc-drawer--nested",k),role:"dialog","aria-modal":"true","aria-label":f,"aria-labelledby":f?void 0:v,onClick:n=>n.stopPropagation(),children:[l("div",{className:e("mc-drawer__header"),children:[l("div",{children:[t("h2",{className:e("mc-drawer__title"),id:v,children:s}),o&&t("div",{className:e("mc-drawer__subtitle"),children:o})]}),t("button",{className:e("mc-button mc-drawer__close"),disabled:i,onClick:r,type:"button","aria-label":N,children:t("i",{className:"bi bi-x-lg","aria-hidden":"true"})})]}),t("div",{className:e("mc-drawer__body",h),ref:T,children:m}),u&&t("div",{className:e("mc-drawer__footer"),children:u})]})]}),document.body)}export{V as McDrawer};
/**
 * Runtime design-system helpers for Modern Commerce React surfaces.
 *
 * @module     local_moderncommerce/design_system
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Reusable portalled drawer for Modern Commerce React admin screens.
 *
 * @module     local_moderncommerce/drawer
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
