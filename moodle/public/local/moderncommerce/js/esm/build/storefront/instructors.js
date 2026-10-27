import{jsx as s,jsxs as e}from"react/jsx-runtime";var m=a=>a.split(/\s+/).filter(Boolean).slice(0,2).map(i=>i.charAt(0).toUpperCase()).join("")||"\u2605";function o({title:a,subtitle:n,instructors:i,style:r}){return!i||i.length===0?null:e("section",{className:"mw-inst",style:r,children:[(a||n)&&e("header",{className:"mw-inst__head",children:[a&&s("h2",{className:"mw-inst__title",children:a}),n&&s("p",{className:"mw-inst__subtitle",children:n})]}),s("div",{className:"mw-inst__grid",children:i.map((t,l)=>e("figure",{className:"mw-inst__card",children:[s("div",{className:"mw-inst__avatar",children:t.photo?s("img",{src:t.photo,alt:t.name,loading:"lazy"}):s("span",{"aria-hidden":"true",children:m(t.name)})}),e("figcaption",{className:"mw-inst__meta",children:[s("span",{className:"mw-inst__name",children:t.name}),t.role&&s("span",{className:"mw-inst__role",children:t.role}),t.bio&&s("p",{className:"mw-inst__bio",children:t.bio})]})]},l))})]})}export{o as default};
/**
 * Instructor spotlight grid for the Modern Commerce storefront.
 *
 * @module     local_moderncommerce/storefront/instructors
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
