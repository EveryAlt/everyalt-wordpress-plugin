/*! For license information please see every-alt-media-button.js.LICENSE.txt */
(()=>{"use strict";var e={251:(e,t,r)=>{var n=r(294),o=Symbol.for("react.element"),u=Symbol.for("react.fragment"),a=Object.prototype.hasOwnProperty,c=n.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,i={key:!0,ref:!0,__self:!0,__source:!0};function s(e,t,r){var n,u={},s=null,f=null;for(n in void 0!==r&&(s=""+r),void 0!==t.key&&(s=""+t.key),void 0!==t.ref&&(f=t.ref),t)a.call(t,n)&&!i.hasOwnProperty(n)&&(u[n]=t[n]);if(e&&e.defaultProps)for(n in t=e.defaultProps)void 0===u[n]&&(u[n]=t[n]);return{$$typeof:o,type:e,key:s,ref:f,props:u,_owner:c.current}}t.Fragment=u,t.jsx=s,t.jsxs=s},408:(e,t)=>{var r=Symbol.for("react.element"),n=Symbol.for("react.portal"),o=Symbol.for("react.fragment"),u=Symbol.for("react.strict_mode"),a=Symbol.for("react.profiler"),c=Symbol.for("react.provider"),i=Symbol.for("react.context"),s=Symbol.for("react.forward_ref"),f=Symbol.for("react.suspense"),l=Symbol.for("react.memo"),p=Symbol.for("react.lazy"),y=Symbol.iterator;var d={isMounted:function(){return!1},enqueueForceUpdate:function(){},enqueueReplaceState:function(){},enqueueSetState:function(){}},b=Object.assign,v={};function m(e,t,r){this.props=e,this.context=t,this.refs=v,this.updater=r||d}function h(){}function _(e,t,r){this.props=e,this.context=t,this.refs=v,this.updater=r||d}m.prototype.isReactComponent={},m.prototype.setState=function(e,t){if("object"!=typeof e&&"function"!=typeof e&&null!=e)throw Error("setState(...): takes an object of state variables to update or a function which returns an object of state variables.");this.updater.enqueueSetState(this,e,t,"setState")},m.prototype.forceUpdate=function(e){this.updater.enqueueForceUpdate(this,e,"forceUpdate")},h.prototype=m.prototype;var S=_.prototype=new h;S.constructor=_,b(S,m.prototype),S.isPureReactComponent=!0;var w=Array.isArray,O=Object.prototype.hasOwnProperty,g={current:null},j={key:!0,ref:!0,__self:!0,__source:!0};function P(e,t,n){var o,u={},a=null,c=null;if(null!=t)for(o in void 0!==t.ref&&(c=t.ref),void 0!==t.key&&(a=""+t.key),t)O.call(t,o)&&!j.hasOwnProperty(o)&&(u[o]=t[o]);var i=arguments.length-2;if(1===i)u.children=n;else if(1<i){for(var s=Array(i),f=0;f<i;f++)s[f]=arguments[f+2];u.children=s}if(e&&e.defaultProps)for(o in i=e.defaultProps)void 0===u[o]&&(u[o]=i[o]);return{$$typeof:r,type:e,key:a,ref:c,props:u,_owner:g.current}}function E(e){return"object"==typeof e&&null!==e&&e.$$typeof===r}var k=/\/+/g;function x(e,t){return"object"==typeof e&&null!==e&&null!=e.key?function(e){var t={"=":"=0",":":"=2"};return"$"+e.replace(/[=:]/g,(function(e){return t[e]}))}(""+e.key):t.toString(36)}function R(e,t,o,u,a){var c=typeof e;"undefined"!==c&&"boolean"!==c||(e=null);var i=!1;if(null===e)i=!0;else switch(c){case"string":case"number":i=!0;break;case"object":switch(e.$$typeof){case r:case n:i=!0}}if(i)return a=a(i=e),e=""===u?"."+x(i,0):u,w(a)?(o="",null!=e&&(o=e.replace(k,"$&/")+"/"),R(a,t,o,"",(function(e){return e}))):null!=a&&(E(a)&&(a=function(e,t){return{$$typeof:r,type:e.type,key:t,ref:e.ref,props:e.props,_owner:e._owner}}(a,o+(!a.key||i&&i.key===a.key?"":(""+a.key).replace(k,"$&/")+"/")+e)),t.push(a)),1;if(i=0,u=""===u?".":u+":",w(e))for(var s=0;s<e.length;s++){var f=u+x(c=e[s],s);i+=R(c,t,o,f,a)}else if(f=function(e){return null===e||"object"!=typeof e?null:"function"==typeof(e=y&&e[y]||e["@@iterator"])?e:null}(e),"function"==typeof f)for(e=f.call(e),s=0;!(c=e.next()).done;)i+=R(c=c.value,t,o,f=u+x(c,s++),a);else if("object"===c)throw t=String(e),Error("Objects are not valid as a React child (found: "+("[object Object]"===t?"object with keys {"+Object.keys(e).join(", ")+"}":t)+"). If you meant to render a collection of children, use an array instead.");return i}function C(e,t,r){if(null==e)return e;var n=[],o=0;return R(e,n,"","",(function(e){return t.call(r,e,o++)})),n}function $(e){if(-1===e._status){var t=e._result;(t=t()).then((function(t){0!==e._status&&-1!==e._status||(e._status=1,e._result=t)}),(function(t){0!==e._status&&-1!==e._status||(e._status=2,e._result=t)})),-1===e._status&&(e._status=0,e._result=t)}if(1===e._status)return e._result.default;throw e._result}var I={current:null},T={transition:null},D={ReactCurrentDispatcher:I,ReactCurrentBatchConfig:T,ReactCurrentOwner:g};t.Children={map:C,forEach:function(e,t,r){C(e,(function(){t.apply(this,arguments)}),r)},count:function(e){var t=0;return C(e,(function(){t++})),t},toArray:function(e){return C(e,(function(e){return e}))||[]},only:function(e){if(!E(e))throw Error("React.Children.only expected to receive a single React element child.");return e}},t.Component=m,t.Fragment=o,t.Profiler=a,t.PureComponent=_,t.StrictMode=u,t.Suspense=f,t.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED=D,t.cloneElement=function(e,t,n){if(null==e)throw Error("React.cloneElement(...): The argument must be a React element, but you passed "+e+".");var o=b({},e.props),u=e.key,a=e.ref,c=e._owner;if(null!=t){if(void 0!==t.ref&&(a=t.ref,c=g.current),void 0!==t.key&&(u=""+t.key),e.type&&e.type.defaultProps)var i=e.type.defaultProps;for(s in t)O.call(t,s)&&!j.hasOwnProperty(s)&&(o[s]=void 0===t[s]&&void 0!==i?i[s]:t[s])}var s=arguments.length-2;if(1===s)o.children=n;else if(1<s){i=Array(s);for(var f=0;f<s;f++)i[f]=arguments[f+2];o.children=i}return{$$typeof:r,type:e.type,key:u,ref:a,props:o,_owner:c}},t.createContext=function(e){return(e={$$typeof:i,_currentValue:e,_currentValue2:e,_threadCount:0,Provider:null,Consumer:null,_defaultValue:null,_globalName:null}).Provider={$$typeof:c,_context:e},e.Consumer=e},t.createElement=P,t.createFactory=function(e){var t=P.bind(null,e);return t.type=e,t},t.createRef=function(){return{current:null}},t.forwardRef=function(e){return{$$typeof:s,render:e}},t.isValidElement=E,t.lazy=function(e){return{$$typeof:p,_payload:{_status:-1,_result:e},_init:$}},t.memo=function(e,t){return{$$typeof:l,type:e,compare:void 0===t?null:t}},t.startTransition=function(e){var t=T.transition;T.transition={};try{e()}finally{T.transition=t}},t.unstable_act=function(){throw Error("act(...) is not supported in production builds of React.")},t.useCallback=function(e,t){return I.current.useCallback(e,t)},t.useContext=function(e){return I.current.useContext(e)},t.useDebugValue=function(){},t.useDeferredValue=function(e){return I.current.useDeferredValue(e)},t.useEffect=function(e,t){return I.current.useEffect(e,t)},t.useId=function(){return I.current.useId()},t.useImperativeHandle=function(e,t,r){return I.current.useImperativeHandle(e,t,r)},t.useInsertionEffect=function(e,t){return I.current.useInsertionEffect(e,t)},t.useLayoutEffect=function(e,t){return I.current.useLayoutEffect(e,t)},t.useMemo=function(e,t){return I.current.useMemo(e,t)},t.useReducer=function(e,t,r){return I.current.useReducer(e,t,r)},t.useRef=function(e){return I.current.useRef(e)},t.useState=function(e){return I.current.useState(e)},t.useSyncExternalStore=function(e,t,r){return I.current.useSyncExternalStore(e,t,r)},t.useTransition=function(){return I.current.useTransition()},t.version="18.2.0"},294:(e,t,r)=>{e.exports=r(408)},893:(e,t,r)=>{e.exports=r(251)}},t={};function r(n){var o=t[n];if(void 0!==o)return o.exports;var u=t[n]={exports:{}};return e[n](u,u.exports,r),u.exports}(()=>{var e=r(893);function t(e){return t="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e},t(e)}function n(e,t){var r=Object.keys(e);if(Object.getOwnPropertySymbols){var n=Object.getOwnPropertySymbols(e);t&&(n=n.filter((function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable}))),r.push.apply(r,n)}return r}function o(e,t,r){return t in e?Object.defineProperty(e,t,{value:r,enumerable:!0,configurable:!0,writable:!0}):e[t]=r,e}function u(e,t){for(var r=0;r<t.length;r++){var n=t[r];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}function a(e,t){return a=Object.setPrototypeOf?Object.setPrototypeOf.bind():function(e,t){return e.__proto__=t,e},a(e,t)}function c(e){var r=function(){if("undefined"==typeof Reflect||!Reflect.construct)return!1;if(Reflect.construct.sham)return!1;if("function"==typeof Proxy)return!0;try{return Boolean.prototype.valueOf.call(Reflect.construct(Boolean,[],(function(){}))),!0}catch(e){return!1}}();return function(){var n,o=i(e);if(r){var u=i(this).constructor;n=Reflect.construct(o,arguments,u)}else n=o.apply(this,arguments);return function(e,r){if(r&&("object"===t(r)||"function"==typeof r))return r;if(void 0!==r)throw new TypeError("Derived constructors may only return object or undefined");return function(e){if(void 0===e)throw new ReferenceError("this hasn't been initialised - super() hasn't been called");return e}(e)}(this,n)}}function i(e){return i=Object.setPrototypeOf?Object.getPrototypeOf.bind():function(e){return e.__proto__||Object.getPrototypeOf(e)},i(e)}var s=wp.i18n.__,f=wp.components,l=(f.BaseControl,f.Button),p=(f.ExternalLink,f.PanelBody,f.PanelRow,f.Placeholder,f.Spinner),y=(f.ToggleControl,f.Notice,f.Snackbar,f.SnackbarList),d=(f.Card,f.CardHeader,f.CardBody,f.CardFooter,f.TextareaControl,wp.element),b=(d.render,d.Component),v=d.Fragment,m=wp.data,h=(m.useDispatch,m.dispatch),_=m.withSelect,S=m.withDispatch,w=wp.compose.compose,O=(wp.notices.store,w(_((function(e){return{notices:e("core/notices").getNotices()}})),S((function(e){return{removeNotice:e("core/notices").removeNotice}})))((function(t){var r=t.notices,n=t.removeNotice,o=r?r.filter((function(e){return"snackbar"===e.type})):[];return(0,e.jsx)(e.Fragment,{children:(0,e.jsx)(y,{className:"every-alt-notices",notices:o,onRemove:n})})}))),g=function(t){!function(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function");e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,writable:!0,configurable:!0}}),Object.defineProperty(e,"prototype",{writable:!1}),t&&a(e,t)}(f,t);var r,n,o,i=c(f);function f(e){var t;return function(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}(this,f),(t=i.apply(this,arguments)).state={isAPILoaded:!1},t}return r=f,(n=[{key:"componentDidMount",value:function(){this.setState({isAPILoaded:!0})}},{key:"generateAltText",value:function(){var e=this;this.setState({isAPILoaded:!1}),wp.apiRequest({path:"every-alt-api/v1/bulk_generate_alt",method:"POST",data:{media_id:this.props.media}}).then((function(t){if(e.setState({isAPILoaded:!0}),t.alt_text){var r=document.getElementById("attachment_alt");r&&(r.value=t.alt_text),h("core/notices").createNotice("success",s("Alt Text Saved","every-alt"),{type:"snackbar",isDismissible:!0,spokenMessage:s("Every Alt","every-alt")})}else h("core/notices").createNotice("success",s("Something went wrong please try later","every-alt"),{type:"snackbar",isDismissible:!0,spokenMessage:s("Every Alt","every-alt")})}))}},{key:"render",value:function(){var t=this;return this.state.isAPILoaded?(0,e.jsxs)(v,{children:[(0,e.jsx)(O,{}),(0,e.jsx)(l,{isPrimary:!0,disabled:this.state.isAPISaving,onClick:function(){return t.generateAltText()},children:s("Generate Alt Text for this Image","every-alt")})]}):(0,e.jsx)(p,{})}}])&&u(r.prototype,n),o&&u(r,o),Object.defineProperty(r,"prototype",{writable:!1}),f}(b);document.addEventListener("DOMContentLoaded",(function(){var t=document.getElementById("every-alt-custom-media-button");if(t){var r=document.getElementById("attachment_alt");r.parentNode.insertBefore(t,r.nextSibling),setTimeout((function(){var t=document.getElementById("every-alt-custom-media-button");ReactDOM.render((0,e.jsx)(g,function(e){for(var t=1;t<arguments.length;t++){var r=null!=arguments[t]?arguments[t]:{};t%2?n(Object(r),!0).forEach((function(t){o(e,t,r[t])})):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(r)):n(Object(r)).forEach((function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(r,t))}))}return e}({},t.dataset)),t)}),100)}}))})()})();