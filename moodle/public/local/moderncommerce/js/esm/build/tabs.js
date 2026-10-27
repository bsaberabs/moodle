import{useEffect as T}from"react";var n=(...a)=>{let t=new Set;return a.forEach(r=>{r&&r.split(/\s+/).filter(Boolean).forEach(l=>{t.add(l)})}),Array.from(t).join(" ")};import{jsx as i,jsxs as m}from"react/jsx-runtime";var f=(a,t)=>`${a}-${t}-tab`,N=(a,t)=>`${a}-${t}-panel`,h=(a,t)=>({role:"tabpanel",id:N(a,t),"aria-labelledby":f(a,t),tabIndex:0});function _({tabs:a,activeKey:t,onChange:r,ariaLabel:l,idPrefix:c="mc-tab",fill:p=!1,className:y}){let u=e=>{let s=a.filter(b=>!b.disabled);if(s.length===0)return;let d=Math.max(0,s.findIndex(b=>b.key===t)),o=d;switch(e.key){case"ArrowRight":case"ArrowDown":o=(d+1)%s.length;break;case"ArrowLeft":case"ArrowUp":o=(d-1+s.length)%s.length;break;case"Home":o=0;break;case"End":o=s.length-1;break;default:return}e.preventDefault();let g=s[o].key;r(g),document.getElementById(f(c,g))?.focus()};return i("ul",{className:n("nav nav-tabs mc-tabs",p&&"nav-fill",y),role:"tablist","aria-label":l,onKeyDown:u,children:a.map(e=>{let s=e.key===t;return i("li",{className:"nav-item",role:"presentation",children:m("button",{id:f(c,e.key),className:n("nav-link mc-tabs__link",s&&"active"),type:"button",role:"tab","aria-selected":s,"aria-controls":N(c,e.key),tabIndex:s?0:-1,disabled:e.disabled,onClick:()=>r(e.key),children:[e.icon&&i("i",{className:`bi ${e.icon} mc-tabs__icon`,"aria-hidden":"true"}),i("span",{className:"mc-tabs__label",children:e.label}),e.badge!==void 0&&e.badge!==null&&e.badge!==""&&i("span",{className:"mc-tabs__badge",children:e.badge})]})},e.key)})})}function K({tabs:a,activeKey:t,onChange:r,title:l,subtitle:c,actions:p,children:y,ariaLabel:u,idPrefix:e="mc-tab",fill:s=!1,className:d,headerClassName:o,bodyClassName:g,tabsClassName:b}){let v=`${e}-title`;return m("section",{className:n("mc-card mc-tab-card",d),"aria-labelledby":v,children:[m("div",{className:n("mc-card-header mc-tab-card__header",o),children:[m("div",{className:n("mc-tab-card__heading"),children:[i("h2",{className:n("mc-card-title mc-tab-card__title"),id:v,children:l}),c&&i("p",{className:n("mc-card-sub mc-tab-card__subtitle"),children:c})]}),p&&i("div",{className:n("mc-tab-card__actions"),children:p})]}),m("div",{className:n("mc-card-body mc-tab-card__body",g),children:[i(_,{tabs:a,activeKey:t,onChange:r,ariaLabel:u,idPrefix:e,fill:s,className:n("mc-tab-card__tabs",b)}),i("div",{className:n("mc-tab-card__panel"),...h(e,t),children:y})]})]})}export{K as McTabCard,_ as McTabs,h as tabPanelProps};
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
