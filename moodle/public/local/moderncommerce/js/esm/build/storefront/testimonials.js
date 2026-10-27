import{jsx as a,jsxs as e}from"react/jsx-runtime";var o=s=>s.split(/\s+/).filter(Boolean).slice(0,2).map(n=>n.charAt(0).toUpperCase()).join("")||"\u2605";function l({rating:s}){return a("span",{className:"mw-tm__stars","aria-label":`${s} / 5`,children:[1,2,3,4,5].map(r=>a("i",{className:`bi ${r<=s?"bi-star-fill":"bi-star"}`,"aria-hidden":"true"},r))})}function p({title:s,subtitle:r,testimonials:i,style:n}){return!i||i.length===0?null:e("section",{className:"mw-tm",style:n,children:[(s||r)&&e("header",{className:"mw-tm__head",children:[s&&a("h2",{className:"mw-tm__title",children:s}),r&&a("p",{className:"mw-tm__subtitle",children:r})]}),a("div",{className:"mw-tm__grid",children:i.map((t,m)=>e("figure",{className:"mw-tm__card",children:[t.rating>0&&a(l,{rating:t.rating}),a("blockquote",{className:"mw-tm__quote",children:t.quote}),e("figcaption",{className:"mw-tm__author",children:[a("span",{className:"mw-tm__avatar","aria-hidden":"true",children:o(t.author)}),e("span",{className:"mw-tm__meta",children:[t.author&&a("span",{className:"mw-tm__name",children:t.author}),t.role&&a("span",{className:"mw-tm__role",children:t.role})]})]})]},m))})]})}export{p as default};
/**
 * Testimonials grid for the Modern Commerce storefront.
 *
 * @module     local_moderncommerce/storefront/testimonials
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
