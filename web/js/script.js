"use strict";var R={get:e=>document.getElementById(e),getQ:e=>document.querySelector(e),make:function(e,...t){const[n,...a]=e.split("."),o=document.createElement(n);return a&&o.classList.add(...a),t&&o.append(...t),o},formatByteSize:function(e){return e>1073741824?(e/1073741824).toFixed(2)+" GB":e>1048576?(e/1048576).toFixed(2)+" MB":e>1024?(e/1024).toFixed(2)+" KB":e+" B"},compileSemanticVersion:function(e){const t=/^(\d+)\.(\d+)\.(\d+)(?:-(dev|pre|rc)\.(\d+))?$/.exec(e);if(!t)return!1;let n=0xffffn;if(t[5]){switch(t[4]){case"dev":n=16384n;break;case"pre":n=32768n;break;case"rc":n=49152n;break;default:return!1}n|=0x0fffn&BigInt(t[5])}return(0xffffn&BigInt(t[1]))<<48n|(0xffffn&BigInt(t[2]))<<32n|(0xffffn&BigInt(t[3]))<<16n|n},onDOMLoaded:function(e){"loading"!==document.readyState?e():document.addEventListener("DOMContentLoaded",e)},msgContainer:null,addMessage:function(e,t,n){n=n||!0;const a=R.make("div."+e);n?(a.textContent=t,a.append(R.make("span.dismiss"))):a.innerHTML=t+'<span class="dismiss"></span>',R.msgContainer.append(a)},markAsErrorElement:function(e){e.classList.add("invalid"),setTimeout(()=>e.classList.remove("invalid"),500)},attachDefaultFailHandler:function(e,t="Request failed"){return e.fail(e=>{let n;try{n=JSON.parse(e.responseText)}catch{R.addMessage(MSG_CLASS_ERROR,"Failed to parse response.",!1),n={reason:e.responseText}}R.addMessage(MSG_CLASS_ERROR,t+(n.reason?": "+n.reason:"."),!0)})},trimLeadingEmptyLines:function(e){let t=e.firstChild;for(;t;)"BR"===t.nodeName||["P","DIV"].includes(t.nodeName)&&!t.textContent?t.remove():e=t,t=e.firstChild},trimTrailingEmptyLines:function(e){let t=e.lastChild;for(;t;)"BR"===t.nodeName||["P","DIV"].includes(t.nodeName)&&!t.textContent?t.remove():e=t,t=e.lastChild},_isSafari:void 0,isSafari:function(){return void 0===R._isSafari&&(R._isSafari=Boolean(navigator.vendor)&&navigator.vendor.includes("Apple")),R._isSafari}};R.msgContainer=R.get("message-container"),R.msgContainer.addEventListener("click",e=>{let t=e.target;t&&t.classList.contains("dismiss")&&(t=t.parentElement,$(t).slideUp(400,()=>t.remove()))});const MSG_CLASS_OK="bg-success.text-success",MSG_CLASS_WARN="bg-warning",MSG_CLASS_ERROR="bg-error.text-error";function attachRemoteSearchHandler(e){let t=null,n=null;const a=e.getElementsByClassName("chosen-search-input")[0],o=e.getElementsByTagName("select")[0],i=o.dataset.url;if(!i)return void console.warn("attachRemoteSearchHandler called on an element who's select does not have a url in its dataset.");const s=o.dataset.ignoreId;a.addEventListener("keydown",()=>{null!==t&&clearTimeout(t),n=t,t=setTimeout(()=>{const e=a.value;if(!e)return void(t=null);const r=n,l=i.replace("{name}",e);$.get(l,i=>{if(n!==r)return;if(!i)return void(t=null);const l=$(o).val();o.replaceChildren(...Array.from(o.querySelectorAll("option:checked")));for(const[e,t]of Object.entries(i)){if(null!=l&&l.includes(e))continue;if(e===s)continue;const n=document.createElement("option");n.value=e,n.textContent=t,o.append(n)}const c=getComputedStyle(a).width;$(o).trigger("chosen:updated"),a.value=e,a.style.width=c,t=null})},500)})}function attachVersionSelectorHandlers(e){const t=e.getElementsByClassName("count-label")[0],n=()=>{const n=e.querySelectorAll("input:checked").length;t.textContent=`${n} Version${1!==n?"s":""} Selected`};e.addEventListener("click",e=>{let t=e.target;if(!t||!t.nodeName)return;if("INPUT"===t.nodeName)return void n();if("DIV"!==t.nodeName||"SPAN"!==t.firstChild?.nodeName){if("SPAN"!==t.nodeName||"DIV"!==t.parentElement?.nodeName)return;t=t.parentElement}e.stopPropagation();const a=t.nextElementSibling,o="INPUT"===a.nodeName?[a]:a.getElementsByTagName("input");let i=!1;for(const e of o)if(!e.checked){i=!0;break}for(const e of o)e.checked=i;n()})}function attachTagVoteButtons(e,t){e.addEventListener("click",e=>{const t=e.target;if(!t||!t.classList)return;const n=t.parentElement,a=n.dataset.tagid;if(!a)return;const o=n.dataset.vote;let i;if(t.classList.contains("add"))i="1"===n.dataset.vote?0:1;else{if(!t.classList.contains("rem"))return;i="-1"===n.dataset.vote?0:-1}n.dataset.vote=String(i);const s=$.ajax(`/api/v2/mods/${modId}/tags/${a}/vote`,{method:"PUT",data:{at:actiontoken,vote:i}});R.attachDefaultFailHandler(s,"Failed to vote").fail(()=>n.dataset.vote=o)});const n=document.getElementById("tag-input-wrap"),a=n.getElementsByTagName("input")[0],o=n.lastElementChild;let i=null,s=null;a.addEventListener("keydown",()=>{null!==i&&clearTimeout(i),s=i,i=setTimeout(()=>{const e=a.value,t=e.slice(e.lastIndexOf(",")+1).trim();if(!t)return void(i=null);const n=s;$.get("/api/v2/tags/by-name/"+t,e=>{if(s===n)if(e){o.replaceChildren();for(const[t,n]of Object.entries(e)){const e=R.make("div.tag-option",n);o.append(e)}i=null}else i=null})},500)}),o.addEventListener("click",e=>{const t=e.target;t&&t.classList&&t.classList.contains("tag-option")&&((e=>{const t=a.value;let n=t.lastIndexOf(",");-1!==n&&t.length>n&&" "===t[n+1]&&n++;const o=t.slice(0,n+1);a.value=o+e+", "})(t.textContent),o.replaceChildren(),a.focus())}),attachDialogSendHandler(t,(e,t)=>{const n=t.get("newTags");if(!n)return R.markAsErrorElement(a),!1;t.delete("newTags");for(let e of n.split(","))e=e.trim(),e&&t.append("tags[]",e);return!0},n=>{R.attachDefaultFailHandler(n,"Failed to add tag").done(n=>{t.getElementsByTagName("button")[0].disabled=!1,a.value="";let o=e.lastElementChild;for("LABEL"===o.previousElementSibling?.nodeName&&(o=o.previousElementSibling);o.previousElementSibling&&o.previousElementSibling.classList.contains("downvoted");)o=o.previousElementSibling;for(const[t,a]of Object.entries(n)){const n=e.querySelector(`.tag[data-tagid="${t}"]`);if(n){n.dataset.vote="1";continue}const i=R.make("a",a.name);i.href="/list/mod?tagids[]="+t;const s=R.make("span.tag",i,R.make("span.add"),R.make("span.rem"));s.style.backgroundColor=a.color,s.dataset.tagid=t,s.dataset.vote="1",e.insertBefore(s,o)}}),t.close()})}function attachDropHandler(e){e.target.addEventListener("dragover",t=>{let n=!1;if(R.isSafari())t.dataTransfer.types.includes("Files")?(t.preventDefault(),t.dataTransfer.dropEffect="copy"):t.dataTransfer.dropEffect="none";else{for(const a of t.dataTransfer.items)if("file"===a.kind&&(t.preventDefault(),n=!0,e.onPrefilter(a)))return void(t.dataTransfer.dropEffect="copy");n&&(t.dataTransfer.dropEffect="none")}}),e.target.addEventListener("drop",t=>{const n=Array.from(t.dataTransfer.files).filter(e.onValidate);if(n.length){t.preventDefault();for(const t of n){const n=new FormData;for(const[t,a]of Object.entries(e.additionalData))n.append(t,a);n.append("file",t);let a=null;const o=new XMLHttpRequest;o.upload.addEventListener("loadstart",n=>{n.file=t,a=e.onStart(n)}),o.upload.addEventListener("progress",n=>{n.file=t,n.userData=a,e.onProgress(n)}),o.addEventListener("loadend",n=>{n.file=t,n.userData=a,e.onComplete(n)}),o.open("POST",e.destUrl,!0),o.send(n)}}})}function attachDialogSendHandler(e,t,n){const a=e.getElementsByTagName("form");if(a.length<1)return void console.warn("Dialog is missing a form",e);const o=a[0];let i;for(const t of e.getElementsByTagName("button"))if("dialog"!==t.formMethod){i=t;break}i?i.addEventListener("click",()=>{const e=new FormData(o,i);if(!t(o,e))return;i.disabled=!0;const a=$.ajax(o.action,{method:o.dataset.method,processData:!1,contentType:!1,data:e});a.fail(()=>i.disabled=!1),n(a)}):console.warn("Dialog is missing a non closing (formmethod!=dialog) button",e)}function attachSpoilerToggle(e){e.click(function(){$(this).toggleClass("expanded")})}var tinymceSettings={plugins:"paste print preview searchreplace autolink autoresize directionality visualblocks visualchars fullscreen image link media code codesample table charmap hr pagebreak nonbreaking anchor toc insertdatetime emoticons advlist lists wordcount imagetools textpattern help spoiler mention noneditable",external_plugins:{mention:"/web/js/tinymce-custom/plugins/mention/plugin.min.js"},toolbar:"formatselect | bold italic strikethrough forecolor backcolor permanentpen formatpainter | link image media pageembed emoticons | alignleft aligncenter alignright alignjustify  | numlist bullist outdent indent | removeformat code | spoiler-add spoiler-remove",toolbar_sticky:!0,image_advtab:!0,importcss_append:!0,height:400,image_caption:!0,convert_urls:!0,relative_urls:!1,remove_script_host:!1,tinycomments_mode:"embedded",content_css:"/web/css/editor_content.css?ver=6",setup:function(e){e.on("change",function(e){tinyMCE.triggerSave(),$("#"+e.target.id).parents("form").trigger("checkform.areYouSure")}),e.on("keyup",function(e){tinyMCE.triggerSave(),$("#"+e.target.id).parents("form").trigger("checkform.areYouSure")})},paste_postprocess:function(e,t){maybePromptForRelativeLinkRemoval(t.node)},mentions:{source:function(e,t){e&&$.getJSON("/api/v2/users/by-name/"+encodeURIComponent(e),function(e){t(Object.entries(e))})},queryBy:1,insert:function(e){return`<a class="mention username mceNonEditable" data-user-hash="${e[0]}" href="/show/user/${e[0]}">${e[1]}</a>`}}},tinymceSettingsCmt={menubar:!1,plugins:"paste searchreplace autolink autoresize directionality image link codesample charmap hr pagebreak nonbreaking anchor emoticons advlist lists wordcount imagetools textpattern help spoiler mention noneditable",external_plugins:tinymceSettings.external_plugins,toolbar:"bold italic strikethrough | link image emoticons | alignleft aligncenter alignright alignjustify  | numlist bullist outdent indent | removeformat | spoiler-add spoiler-remove",toolbar_sticky:!0,image_advtab:!0,importcss_append:!0,min_height:200,height:200,image_caption:!0,convert_urls:!0,relative_urls:!1,remove_script_host:!1,tinycomments_mode:"embedded",paste_data_images:!0,content_css:tinymceSettings.content_css,setup:function(e){tinymceSettings.setup(e),e.on("SetContent",function(t){if(!t.initial&&t.paste&&wrapNextPaste){wrapNextPaste=0;const t=e.dom.select(".spoiler");e.selection.setNode(t[t.length-1]),e.selection.collapse(!1),e.focus()}})},paste_preprocess:function(e,t){const n=t.content;n&&(t.content=n.trim().replaceAll("\r\n","\n"),couldBeCrashReport(n)?confirm('Whoa there, looks like you pasted a crash report.\nShould we wrap that for you, so its easier to read for the Modder?\n\nPressing "cancel" (or the equivalent in your language) will paste the text as-is.')&&(wrapNextPaste=2):n.length>=1e3&&!n.includes("base64,")&&confirm('Whoa there, looks like you pasted a lot of text at once.\nShould we wrap that for you, so its easier to read for the Modder?\n\nPressing "cancel" (or the equivalent in your language) will paste the text as-is.')&&(wrapNextPaste=1))},paste_postprocess:function(e,t){if(R.trimLeadingEmptyLines(t.node),R.trimTrailingEmptyLines(t.node),wrapNextPaste){const e=wrapAsSpoilerForTMCE(t.node.childNodes,2===wrapNextPaste);t.node.replaceChildren(e)}maybePromptForRelativeLinkRemoval(t.node)},mentions:tinymceSettings.mentions};function maybePromptForRelativeLinkRemoval(e){const t=e.querySelectorAll('a[href^="./"], a[href^="../"], a[href^="/"][href*="/issues/"]');if(t.length){let e,n=t[0].getAttribute("href");try{e=new URL(n,document.baseURI).href}catch{e="<link was malformed>"}if(confirm(`Looks like there are relative links in that html you've just pasted in here - we found ${t.length} such link${1===t.length?"":"s"}.\nUnless you also copied it from here, those probably wont link to the page you want.\n\nHere is the first one and where it would link to:\n\t${t[0].textContent}\n\tlinks to '${n}',\n\twhich actually resolves to '${e}'\n\nSince we cannot determine where this was copied from, we cannot automatically fix the links for you - we can however at least remove them from the text.\nShould we do that?\n\nPressing "cancel" (or the equivalent in your language) will paste the text as-is.`))for(const e of t)e.parentElement.replaceChild(R.make("span",...e.childNodes),e)}}let wrapNextPaste=0;function couldBeCrashReport(e){return e.includes("System.Exception")||e.includes("at Vintagestory.")||e.includes("Event Log")||e.includes("Critical error")}function wrapAsSpoilerForTMCE(e,t){const n=R.make("div.spoiler-toggle",t?"Crash Report":"Spoiler");n.setAttribute("contenteditable","true");const a=R.make(t?"pre.spoiler-text":"div.spoiler-text");a.setAttribute("contenteditable","true");for(const t of e)t.style&&(t.style.whiteSpace="",t.style.font="");a.append(...e);const o=R.make("div.spoiler");return t&&o.classList.add("crash-report"),o.setAttribute("contenteditable","false"),o.append(n,a),o}function createEditor(e,t){e.id||(e.id="editor"+Math.floor(1e4*Math.random())),(t=Object.assign({},t)).target=e,tinyMCE.init(t)}function destroyEditor(e){e.each(function(){tinyMCE.remove("#"+this.id)})}function getEditorContents(e){return tinyMCE.triggerSave(),e.val()}function attachCommentHandlers(){const e=document.getElementsByClassName("comments")[0];function t(){const t="flat"===$.cookie("commentstructure"),n="oldestfirst"===$.cookie("commentsort"),a=Array.from(e.getElementsByClassName("comment"));let o;if(t)o=n?a.sort(function(e,t){var n=parseInt(e.dataset.stamp)-parseInt(t.dataset.stamp);return n<0?-1:n>0?1:0}):a.sort(function(e,t){var n=parseInt(t.dataset.stamp)-parseInt(e.dataset.stamp);return n<0?-1:n>0?1:0}),e.replaceChildren(...o);else{e.replaceChildren();const t=a.sort(function(e,t){var n=parseFloat(e.dataset.order)-parseFloat(t.dataset.order);return n<0?-1:n>0?1:0}),i=[],s=R.make("div");let r=s;for(const e of t){const t=parseInt(e.dataset.d);for(;i.length&&i[i.length-1]>=t;i.pop())r=r.parentElement;if(parseInt(e.dataset.cldn)){const e=R.make("div.convo");r.appendChild(e),r=e,i.push(t)}r.appendChild(e)}o=Array.from(s.children),o=n?o.sort(function(e,t){var n=parseInt(e.dataset.stamp||e.firstElementChild.dataset.stamp)-parseInt(t.dataset.stamp||t.firstElementChild.dataset.stamp);return n<0?-1:n>0?1:0}):o.sort(function(e,t){var n=parseInt(t.dataset.stamp||t.firstElementChild.dataset.stamp)-parseInt(e.dataset.stamp||e.firstElementChild.dataset.stamp);return n<0?-1:n>0?1:0}),e.replaceChildren(...o)}}R.get("cmt-ord-desc").addEventListener("click",n=>{n.preventDefault(),e.classList.remove("asc"),e.classList.add("desc"),$.cookie("commentsort","newestfirst",{expires:365,path:"/"}),t()}),R.get("cmt-ord-asc").addEventListener("click",n=>{n.preventDefault(),e.classList.remove("desc"),e.classList.add("asc"),$.cookie("commentsort","oldestfirst",{expires:365,path:"/"}),t()}),R.get("cmt-threaded").addEventListener("click",n=>{n.preventDefault(),e.classList.add("threaded"),$.cookie("commentstructure","threaded",{expires:365,path:"/"}),t()}),R.get("cmt-flat").addEventListener("click",n=>{n.preventDefault(),e.classList.remove("threaded"),$.cookie("commentstructure","flat",{expires:365,path:"/"}),t()}),"flat"===$.cookie("commentstructure")&&t();const n=e.getElementsByClassName("comment-editor")[0];let a=!1;if($("textarea",n).focus(function(e){if(a)return;a=!0,$(this).removeClass("whitetext"),$("form[name=commentformtemplate]").trigger("reinitialize.areYouSure");const t=e.currentTarget;createEditor(t,tinymceSettingsCmt),setTimeout(()=>tinyMCE.get(t.id).focus(),100)}),$("button[name='save']",n).click(function(e){const t=$(this).parents(".comment.comment-editor")[0],n=t.getElementsByTagName("textarea")[0],a=getEditorContents($(n));if(!a)return;const i=e.target;i.disabled=!0;const l=i.textContent,c=r(i),d=$.ajax({url:`/api/v2/mods/${modId}/comments?at=`+actiontoken,method:"PUT",data:a,contentType:"text/html",dataType:"text"}).done(function(e,a,r){const d=s(r.getResponseHeader("Location").slice(5),0,e,0);t.after(d),tinyMCE.get(n.id).setContent(""),o(d),clearInterval(c),i.textContent=l,i.disabled=!1}).fail(function(){clearInterval(c),i.textContent=l,i.disabled=!1});R.attachDefaultFailHandler(d,"Failed to submit comment")}),$(".comment.comment-editor",e).show(),$(e).on("click",'a[href="#r"]',function(t){t.preventDefault();let n=$(this).parents(".comment")[0];const a=n.id.split("-")[1],l="repl-"+a,c=document.getElementById(l);if(c){const e=c.getElementsByTagName("form")[0];return e.classList.contains("dirty")&&!confirm("Discard changed comment data?")||(destroyEditor($("textarea",e)),c.remove()),!1}let d=parseInt(n.dataset.d)+1;d=Math.min(d,10);const m=$(`\n<div id="${l}" class="comment comment-editor editbox rsp" data-d="${d}">\n\t<div class="title">Response to comment:</div>\n\t<a class="reference" href="#${n.id}"><span></span></a>\n\t<div class="body"></div>\n</div>\n`)[0],u=n.getElementsByClassName("body")[0].textContent.substring(0,255);m.getElementsByTagName("span")[0].textContent=u;for(const e of n.getElementsByClassName("title")[0].getElementsByTagName("a"))if(e.href.includes("user")){m.getElementsByClassName("title")[0].textContent=`Respond to ${e.textContent}'s comment:`;break}let f=0;const p="oldestfirst"===$.cookie("commentsort");if("flat"!==$.cookie("commentstructure")){let e=n.parentElement;if(!e.classList.contains("convo")||!parseInt(n.dataset.cldn)){e=R.make("div.convo");const t=n;n.replaceWith(e),e.appendChild(t),n=t}if(p){let t=e;for(;!t.classList.contains("comment");)t=t.lastElementChild;f=parseFloat(t.dataset.order),e.appendChild(m)}else{let t=e;for(;!t.classList.contains("comment");)t=t.firstElementChild;f=parseFloat(t.dataset.order),e.insertBefore(m,e.firstElementChild)}}else p?e.append(m):e.insertBefore(m,e.firstElementChild.nextElementSibling);return m.scrollIntoView({behavior:"smooth",block:"nearest"}),i(m.getElementsByClassName("body")[0],"Add Response","",(e,t,i,l)=>{e.disabled=!0;const c=e.textContent,p=r(e),g=$.ajax({url:`/api/v2/mods/${modId}/comments?response-to=${a}&at=`+actiontoken,method:"PUT",data:t,contentType:"text/html",dataType:"text"}).done(function(e,t,a){clearInterval(p);const i=s(a.getResponseHeader("Location").slice(5),d,e,f+.01),r=n.getElementsByTagName("a")[1].textContent,c=R.make("a.reference","@"+r+": ",R.make("span",u));c.href="#"+n.id,i.getElementsByClassName("title")[0].after(c),n.dataset.cldn=String(parseInt(n.dataset.cldn)+1),tinyMCE.remove("#"+l.id),m.replaceWith(i),o(i)}).fail(function(){clearInterval(p),e.textContent=c,e.disabled=!1});R.attachDefaultFailHandler(g,"Failed to submit comment")}),!1}),$(e).on("click",'a[href="#e"]',function(e){e.preventDefault();const t=$(this).parents(".comment"),n=$(".body",t);if(1==t.data("editing")){const e=t.find("form");return e.hasClass("dirty")&&!confirm("Discard changed comment data?")||(destroyEditor($("textarea",t)),e.remove(),n.show(),t.data("editing",0),$("form[name=commentformedit]").trigger("reinitialize.areYouSure")),!1}n.hide(),t.data("editing",1);const a=t[0].id.split("-")[1];return i(t[0],"Update Comment",n.html(),(e,o,i,s)=>{const r=$.ajax({url:`/api/v2/comments/${a}?at=`+actiontoken,method:"POST",data:o,contentType:"text/html",dataType:"json"}).done(function(e){tinyMCE.remove("#"+s.id),i.remove(),n.html(e.html),attachSpoilerToggle($(".spoiler-toggle",n)),t.data("editing",0),n.show()});R.attachDefaultFailHandler(r,"Failed to edit comment")}),!1}),$(e).on("click",'a[href="#d"]',function(e){if(e.preventDefault(),confirm("Are you sure you want to delete this comment?")){const e=$(this).parents(".comment");e.hide();const t=e[0].id.split("-")[1],n=$.ajax({url:`/api/v2/comments/${t}?at=`+actiontoken,method:"DELETE"});R.attachDefaultFailHandler(n,"Failed to delete comment").fail(()=>e.show())}}),$(e).on("click",'a[href^="#cmt-"]',function(){const e=this.getAttribute("href");if(!e)return;const t=e.slice(1);var n=document.getElementById(t);setTimeout(()=>o(n),100)}),"#cmt"===document.location.hash.split("-")[0]){const e=document.getElementById(document.location.hash.substring(1));e&&o(e)}function o(e){e.classList.add("highlight"),setTimeout(()=>e.classList.remove("highlight"),2e3)}function i(e,t,n,a){const o=$(`\n\t\t\t<form name="commentformedit" onsubmit="return false;">\n\t\t\t\t<textarea name="commenttext" class="editor editcommenteditor" data-editorname="editcomment" style="width: 100%; height: 135px;"></textarea>\n\t\t\t\t<p style="margin:4px; margin-top:5px;"><button class="shine" type="submit" name="save">${t}</button></p>\n\t\t\t</form>\n\t\t`)[0];o.getElementsByTagName("textarea")[0].textContent=n,e.append(o),$(o).areYouSure();const i=o.getElementsByTagName("textarea")[0];createEditor(i,tinymceSettingsCmt),setTimeout(()=>tinyMCE.get(i.id).focus(),100);const s=o.querySelector("button[name='save']");s.addEventListener("click",e=>{e.preventDefault();var t=getEditorContents($(i));a(s,t,o,i)})}function s(e,t,n,a){const o=R.get("account-menu"),i=o.firstElementChild.textContent,s=o.lastElementChild.firstElementChild.getAttribute("href"),r=$(`\n<div id="cmt-${e}" class="editbox comment${t?" rsp":""}" data-order="${a}" data-stamp="${Date.now()}"${t?' data-d="'+t+'"':""} data-cldn="0">\n\t<div class="title">\n\t\t<span><a style="text-decoration:none;" class="cmt-pinner" href="#cmt-${e}"><i class="bx bx-link-alt"></i></a> <a href="${s}">${i}</a>, just now</span>\n\t\t<span class="buttons">(<a href="#r" onclick="return false;">respond</a>&nbsp;<a href="#e" onclick="return false;">edit</a>&nbsp;<a href="#d" onclick="return false;">delete</a>)</span>\n\t</div>\n\t<div class="body">${n}</div>\n</div>\n`)[0];return attachSpoilerToggle($(".spoiler-toggle",$(r))),r}function r(e){e.textContent="Submitting..";let t=0;return setInterval(()=>{e.textContent="Submitting....".slice(0,12+t),t=(t+1)%3},300)}}!function(e){e.fn.areYouSure=function(t){var n=e.extend({message:"You have unsaved changes!",dirtyClass:"dirty",change:null,silent:!1,addRemoveFieldsMarksDirty:!1,fieldEvents:"change keyup propertychange input",fieldSelector:":input:not(input[type=submit]):not(input[type=button])"},t),a=function(t){if(t.hasClass("ays-ignore")||t.hasClass("aysIgnore")||t.attr("data-ays-ignore")||void 0===t.attr("name"))return null;if(t.is(":disabled"))return"ays-disabled";var n,a=t.attr("type");switch(t.is("select")&&(a="select"),a){case"checkbox":case"radio":n=t.is(":checked");break;case"select":n="",t.find("option").each(function(){var t=e(this);t.is(":selected")&&(n+=t.val())});break;default:n=t.val()}return n},o=function(e){e.data("ays-orig",a(e))},i=function(t){var o=function(e){var t=e.data("ays-orig");return void 0!==t&&a(e)!=t},i=e(this).is("form")?e(this):e(this).parents("form");if(o(e(t.target)))r(i,!0);else{var s=i.find(n.fieldSelector);if(n.addRemoveFieldsMarksDirty&&i.data("ays-orig-field-count")!=s.length)return void r(i,!0);var l=!1;s.each(function(){var t=e(this);if(o(t))return l=!0,!1}),r(i,l)}},s=function(t){var a=t.find(n.fieldSelector);e(a).each(function(){o(e(this))}),e(a).unbind(n.fieldEvents,i),e(a).bind(n.fieldEvents,i),t.data("ays-orig-field-count",e(a).length),r(t,!1)},r=function(e,t){var a=t!=e.hasClass(n.dirtyClass);e.toggleClass(n.dirtyClass,t),a&&(n.change&&n.change.call(e,e),t&&e.trigger("dirty.areYouSure",[e]),t||e.trigger("clean.areYouSure",[e]),e.trigger("change.areYouSure",[e]))},l=function(){var t=e(this),a=t.find(n.fieldSelector);e(a).each(function(){var t=e(this);t.data("ays-orig")||(o(t),t.bind(n.fieldEvents,i))}),t.trigger("checkform.areYouSure")},c=function(){s(e(this))};return n.silent||window.aysUnloadSet||(window.aysUnloadSet=!0,e(window).bind("beforeunload",function(){if(0!=e("form").filter("."+n.dirtyClass).length){if(navigator.userAgent.toLowerCase().match(/msie|chrome/)){if(window.aysHasPrompted)return;window.aysHasPrompted=!0,window.setTimeout(function(){window.aysHasPrompted=!1},900)}return n.message}})),this.each(function(){if(e(this).is("form")){var t=e(this);t.submit(function(){t.removeClass(n.dirtyClass)}),t.bind("reset",function(){r(t,!1)}),t.bind("rescan.areYouSure",l),t.bind("reinitialize.areYouSure",c),t.bind("checkform.areYouSure",i),s(t)}})}}(jQuery),R.onDOMLoaded(function(){$("select").each(function(){if(0==$(this).parents(".template").length){var e="noSearch"==$(this).attr("noSearch");$(this).chosen({placeholder_text_multiple:" ",disable_search:e})}}),$("form[name=form1]").areYouSure();for(const e of document.querySelectorAll("[data-opens-dialog]"))e.addEventListener("click",e=>{const t=e.currentTarget.dataset.opensDialog;let n;t&&(n=R.get(t))?n.showModal():console.warn("Failed to find dialog to open",t)});attachSpoilerToggle($(".spoiler-toggle"))}),$(function(){navigator.userAgent.toLowerCase().match(/iphone|ipad|ipod|opera/)&&$("a").bind("click",function(e){var t=$(e.target).closest("a").attr("href");if(void 0!==t&&!t.match(/^#/)&&""!=t.trim()){var n=$(window).triggerHandler("beforeunload",n);return n&&""!=n&&!confirm(n+"\n\nPress OK to leave this page or Cancel to stay.")||(window.location.href=t),!1}})});
"use strict";
function toggleGalleryFullscreen(wrap) {
    const stage = wrap.querySelector('.gallery-stage');
    if (wrap.classList.contains('is-fullscreen')) {
        stage.style.scrollBehavior = 'auto';
        wrap.classList.remove('is-fullscreen');
        document.body.style.overflow = '';
        stage.style.width = wrap.style.getPropertyValue('--gallery-w');
        stage.style.height = wrap.style.getPropertyValue('--gallery-h');
        stage.scrollLeft = stage.scrollLeft;
        setTimeout(() => { stage.style.scrollBehavior = ''; }, 50);
    }
    else {
        stage.style.scrollBehavior = 'auto';
        wrap.classList.add('is-fullscreen');
        document.body.style.overflow = 'hidden';
        stage.style.width = '100%';
        stage.style.height = '100%';
        stage.scrollLeft = stage.scrollLeft;
        setTimeout(() => { stage.style.scrollBehavior = ''; }, 50);
    }
}
function initGallery() {
    const wrap = document.querySelector('.imageslideshow');
    if (!wrap)
        return;
    const stage = wrap.querySelector('.gallery-stage');
    const slides = stage.children;
    if (slides.length === 0)
        return;
    // Escape key exits fullscreen
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (wrap.classList.contains('is-fullscreen'))
                toggleGalleryFullscreen(wrap);
        }
    });
    // Fullscreen button
    const full = wrap.querySelector('.gallery-fullscreen');
    full.onclick = toggleGalleryFullscreen.bind(null, wrap);
    // Prevent inline image clicks from opening new tab
    stage.addEventListener('click', function (e) {
        const link = e.target.closest('a');
        if (link)
            e.preventDefault();
    });
    if (slides.length < 2)
        return;
    const prev = wrap.querySelector('.gallery-arr.prev');
    const next = wrap.querySelector('.gallery-arr.next');
    let current = 0;
    let userInteracted = false;
    // Arrow navigation
    const border = wrap.querySelector('.gallery-thumb-border');
    function updateArrows() {
        if (prev)
            prev.style.visibility = current === 0 ? 'hidden' : '';
        if (next)
            next.style.visibility = current === slides.length - 1 ? 'hidden' : '';
    }
    function updateBorder() {
        if (border) {
            const thumbWidth = 64 + 2;
            border.style.transform = 'translate3d(' + (current * thumbWidth) + 'px, 0, 0)';
        }
    }
    const storageKey = 'gallery-' + location.pathname;
    let isNavigating = false;
    function goTo(idx) {
        current = idx;
        isNavigating = true;
        sessionStorage.setItem(storageKey, String(idx));
        updateBorder();
        updateArrows();
        slides[idx].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
    }
    prev.onclick = () => { userInteracted = true; if (current > 0)
        goTo(current - 1); };
    next.onclick = () => { userInteracted = true; if (current < slides.length - 1)
        goTo(current + 1); };
    // Thumbnail click
    const shaft = wrap.querySelector('.gallery-nav-shaft');
    if (shaft)
        shaft.onclick = (e) => {
            const btn = e.target.closest('.gallery-thumb');
            if (!btn)
                return;
            userInteracted = true;
            goTo(parseInt(btn.dataset.i, 10));
        };
    // Drag-to-pan
    let dragStartX = 0;
    let dragScrollLeft = 0;
    let isDragging = false;
    let didDrag = false;
    stage.addEventListener('mousedown', (e) => {
        isDragging = true;
        didDrag = false;
        dragStartX = e.pageX;
        dragScrollLeft = stage.scrollLeft;
        stage.style.cursor = 'grabbing';
        e.preventDefault(); // suppress native image/link drag
    });
    document.addEventListener('mousemove', (e) => {
        if (!isDragging)
            return;
        const dx = e.pageX - dragStartX;
        if (!didDrag && Math.abs(dx) > 5) {
            didDrag = true;
            stage.style.scrollSnapType = 'none';
            stage.style.scrollBehavior = 'auto';
        }
        if (didDrag)
            stage.scrollLeft = dragScrollLeft - dx;
    });
    document.addEventListener('mouseup', () => {
        if (!isDragging)
            return;
        isDragging = false;
        stage.style.cursor = '';
        if (!didDrag)
            return;
        userInteracted = true;
        stage.style.scrollSnapType = '';
        stage.style.scrollBehavior = '';
        const idx = Math.round(stage.scrollLeft / stage.offsetWidth);
        goTo(Math.max(0, Math.min(idx, slides.length - 1)));
    });
    // Suppress click after a drag
    stage.addEventListener('click', (e) => {
        if (didDrag) {
            e.preventDefault();
            e.stopPropagation();
        }
    }, true);
    // Horizontal scroll (deltaX) navigates one slide at a time
    let wheelLock = false;
    stage.addEventListener('wheel', (e) => {
        if (!e.deltaX || Math.abs(e.deltaY) >= Math.abs(e.deltaX))
            return;
        e.preventDefault();
        userInteracted = true;
        if (wheelLock)
            return;
        wheelLock = true;
        const direction = e.deltaX > 0 ? 1 : -1;
        const target = current + direction;
        if (target >= 0 && target < slides.length)
            goTo(target);
        setTimeout(() => { wheelLock = false; }, 300);
    });
    // Sync on scroll end
    let isAutoScrolling = false; // set for any programmatic scroll that should not stop autoplay (autoplay tick + restoration)
    stage.addEventListener('scrollend', () => {
        if(isAutoScrolling) { isAutoScrolling = false; return; }
        if(isNavigating) { isNavigating = false; return; }
        userInteracted = true;
        const idx = Math.round(stage.scrollLeft / stage.offsetWidth);
        if (idx !== current) {
            current = idx;
            updateBorder();
            updateArrows();
        }
    });
    // Auto-play: advance every 5s until user interacts
    const autoplay = setInterval(() => {
        if (userInteracted) {
            clearInterval(autoplay);
            return;
        }
        isAutoScrolling = true;
        goTo((current + 1) % slides.length);
    }, 5000);
    // Initial state — restore from sessionStorage (scroll-snap resets scrollLeft on reload)
    const saved = sessionStorage.getItem(storageKey);
    if(saved) {
        const idx = parseInt(saved, 10);
        if(idx > 0 && idx < slides.length) {
            current = idx;
            isAutoScrolling = true;
            stage.style.scrollBehavior = 'auto';
            slides[idx].scrollIntoView({block: 'nearest', inline: 'start'});
            stage.style.scrollBehavior = '';
        }
    }
    if (border) border.style.transition = 'none';
    updateBorder();
    updateArrows();
    requestAnimationFrame(() => { if (border) border.style.transition = ''; });
}
