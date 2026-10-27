import{useEffect as h,useRef as T}from"react";import{useEffect as w}from"react";var s=(...i)=>{let o=new Set;return i.forEach(r=>{r&&r.split(/\s+/).filter(Boolean).forEach(b=>{o.add(b)})}),Array.from(o).join(" ")};import{jsx as u,jsxs as N}from"react/jsx-runtime";var g=(i,o)=>`${i}-${o}-tab`,v=(i,o)=>`${i}-${o}-panel`,k=(i,o)=>({role:"tabpanel",id:v(i,o),"aria-labelledby":g(i,o),tabIndex:0});function _({tabs:i,activeKey:o,onChange:r,ariaLabel:b,idPrefix:a="mc-tab",fill:p=!1,className:m}){let l=t=>{let n=i.filter(y=>!y.disabled);if(n.length===0)return;let e=Math.max(0,n.findIndex(y=>y.key===o)),c=e;switch(t.key){case"ArrowRight":case"ArrowDown":c=(e+1)%n.length;break;case"ArrowLeft":case"ArrowUp":c=(e-1+n.length)%n.length;break;case"Home":c=0;break;case"End":c=n.length-1;break;default:return}t.preventDefault();let f=n[c].key;r(f),document.getElementById(g(a,f))?.focus()};return u("ul",{className:s("nav nav-tabs mc-tabs",p&&"nav-fill",m),role:"tablist","aria-label":b,onKeyDown:l,children:i.map(t=>{let n=t.key===o;return u("li",{className:"nav-item",role:"presentation",children:N("button",{id:g(a,t.key),className:s("nav-link mc-tabs__link",n&&"active"),type:"button",role:"tab","aria-selected":n,"aria-controls":v(a,t.key),tabIndex:n?0:-1,disabled:t.disabled,onClick:()=>r(t.key),children:[t.icon&&u("i",{className:`bi ${t.icon} mc-tabs__icon`,"aria-hidden":"true"}),u("span",{className:"mc-tabs__label",children:t.label}),t.badge!==void 0&&t.badge!==null&&t.badge!==""&&u("span",{className:"mc-tabs__badge",children:t.badge})]})},t.key)})})}import{Fragment as x,jsx as d,jsxs as C}from"react/jsx-runtime";function B({value:i,onChange:o,mode:r,onModeChange:b,labels:a,idPrefix:p="mc-email-body-editor"}){let m=T(null);h(()=>{let e=m.current;!e||document.activeElement===e||e.innerHTML!==i&&(e.innerHTML=i||"")},[i,r]);let l=(e,c)=>{m.current?.focus(),document.execCommand(e,!1,c),o(m.current?.innerHTML||"")},t=()=>{let e=window.prompt(a.linkurl||"Link URL");e&&l("createLink",e)},n=[{key:"bold",icon:"bi-type-bold",label:a.formatbold||"Bold",onClick:()=>l("bold")},{key:"italic",icon:"bi-type-italic",label:a.formatitalic||"Italic",onClick:()=>l("italic")},{key:"bullet",icon:"bi-list-ul",label:a.formatbulletlist||"Bulleted list",onClick:()=>l("insertUnorderedList")},{key:"numbered",icon:"bi-list-ol",label:a.formatnumberedlist||"Numbered list",onClick:()=>l("insertOrderedList")},{key:"link",icon:"bi-link-45deg",label:a.formatlink||"Link",onClick:t},{key:"unlink",icon:"bi-link",label:a.formatunlink||"Remove link",onClick:()=>l("unlink")},{key:"clear",icon:"bi-eraser",label:a.formatclear||"Clear formatting",onClick:()=>l("removeFormat")}];return C("div",{className:s("mc-email-body-editor"),children:[d(_,{activeKey:r,ariaLabel:a.bodyeditormode||"Body editor mode",className:"mc-email-body-editor__tabs",idPrefix:p,onChange:b,tabs:[{key:"visual",label:a.bodyvisual||"Visual editor",icon:"bi-window"},{key:"html",label:a.bodyhtml||"HTML",icon:"bi-code-slash"}]}),d("div",{className:s("mc-email-body-editor__panel"),...k(p,r),children:r==="visual"?C(x,{children:[d("div",{className:s("mc-email-body-editor__toolbar"),"aria-label":a.formattoolbar||"Formatting toolbar",children:n.map(e=>d("button",{"aria-label":e.label,className:s("mc-button mc-email-body-editor__tool"),onMouseDown:c=>c.preventDefault(),onClick:e.onClick,title:e.label,type:"button",children:d("i",{className:`bi ${e.icon}`,"aria-hidden":"true"})},e.key))}),d("div",{className:s("mc-form-control mc-email-body-editor__visual"),contentEditable:!0,onInput:e=>o(e.currentTarget.innerHTML),ref:m,role:"textbox","aria-label":a.body,suppressContentEditableWarning:!0})]}):d("textarea",{className:s("form-control form-control-sm mc-code-textarea mc-email-body-editor__html"),onChange:e=>o(e.target.value),rows:14,value:i})})]})}export{B as EmailBodyEditor};
/**
 * Runtime design-system helpers for Modern Commerce React surfaces.
 *
 * @module     local_moderncommerce/design_system
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Reusable Modern Commerce tab navigation for React screens. Emits proper
 * Bootstrap `nav nav-tabs` markup with full WAI-ARIA tab semantics (roving
 * tabindex + arrow-key navigation), reskinned to the mc-* design system.
 *
 * @module     local_moderncommerce/tabs
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Shared Modern Commerce email body editor.
 *
 * Stores and returns HTML, while giving non-technical admins a visual editing
 * surface for common formatting tasks.
 *
 * @module     local_moderncommerce/email_body_editor
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
