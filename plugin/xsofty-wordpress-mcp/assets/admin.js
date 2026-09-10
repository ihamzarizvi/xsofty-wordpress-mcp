(function(){
'use strict';
var dataNode=document.getElementById('xwmcp-preset-data');
if(!dataNode)return;
var presets={};
try{presets=JSON.parse(dataNode.textContent||'{}');}catch(error){return;}

function setChecked(inputs,values,allWhenEmpty){
 var selected=new Set(values||[]);
 inputs.forEach(function(input){input.checked=allWhenEmpty&&selected.size===0?true:selected.has(input.value);});
}
function refreshPicker(picker){
 var tools=Array.prototype.slice.call(picker.querySelectorAll('.xwmcp-tool'));
 var visible=tools.filter(function(tool){return !tool.hidden;});
 var checked=tools.filter(function(tool){return tool.querySelector('input').checked;});
 var count=picker.querySelector('.xwmcp-tool-count');
 if(count)count.textContent=checked.length+' selected, '+visible.length+' shown';
 picker.querySelectorAll('[data-tool-group]').forEach(function(group){
  var shown=Array.prototype.some.call(group.querySelectorAll('.xwmcp-tool'),function(tool){return !tool.hidden;});
  group.hidden=!shown;
  if(shown&&picker.querySelector('.xwmcp-tool-search')&&picker.querySelector('.xwmcp-tool-search').value.trim()!=='')group.open=true;
 });
}
function activateCustom(form){
 var current=form.querySelector('input[name="preset"]:checked');
 if(current&&current.value!=='custom'){
  var custom=form.querySelector('input[name="preset"][value="custom"]');
  if(custom){custom.checked=true;updatePresetSummary(form,'custom');}
 }
}
function updatePresetSummary(form,key){
 var summary=form.querySelector('[data-preset-summary]');
 if(!summary||!presets[key])return;
 var preset=presets[key];
 summary.textContent=preset.label+': '+preset.description;
 form.classList.toggle('is-custom',key==='custom');
}
function applyPreset(form,key){
 var preset=presets[key];if(!preset)return;
 updatePresetSummary(form,key);
 if(key==='custom')return;
 setChecked(Array.prototype.slice.call(form.querySelectorAll('input[name="scopes[]"]')),preset.scopes,false);
 setChecked(Array.prototype.slice.call(form.querySelectorAll('input[name="allowed_tools[]"]')),preset.tools,key==='administrator');
 form.querySelectorAll('[data-tool-picker]').forEach(refreshPicker);
}

document.querySelectorAll('[data-connection-form]').forEach(function(form){
 var selected=form.querySelector('input[name="preset"]:checked');
 if(selected)updatePresetSummary(form,selected.value);
 form.querySelectorAll('input[name="preset"]').forEach(function(radio){radio.addEventListener('change',function(){applyPreset(form,radio.value);});});
 form.querySelectorAll('input[name="scopes[]"],input[name="allowed_tools[]"]').forEach(function(input){input.addEventListener('change',function(){activateCustom(form);});});
});

document.querySelectorAll('[data-tool-picker]').forEach(function(picker){
 var search=picker.querySelector('.xwmcp-tool-search');
 if(search)search.addEventListener('input',function(){
  var term=search.value.trim().toLowerCase();
  picker.querySelectorAll('.xwmcp-tool').forEach(function(tool){tool.hidden=term!==''&&!(tool.getAttribute('data-tool-search-text')||'').includes(term);});
  refreshPicker(picker);
 });
 picker.querySelectorAll('.xwmcp-tool input').forEach(function(input){input.addEventListener('change',function(){refreshPicker(picker);});});
 refreshPicker(picker);
});

document.querySelectorAll('[data-global-preset]').forEach(function(button){
 button.addEventListener('click',function(){
  var preset=presets[button.getAttribute('data-global-preset')];if(!preset)return;
  var form=button.closest('form');var inputs=Array.prototype.slice.call(form.querySelectorAll('input[name="enabled_tools[]"]'));
  setChecked(inputs,preset.tools,button.getAttribute('data-global-preset')==='administrator');
  var picker=form.querySelector('[data-tool-picker]');if(picker)refreshPicker(picker);
  button.blur();
 });
});

document.querySelectorAll('[data-copy-target]').forEach(function(button){
 button.addEventListener('click',function(){
  var target=document.getElementById(button.getAttribute('data-copy-target'));if(!target)return;
  var text=target.textContent||'';var original=button.textContent;
  function done(){button.textContent='Copied';window.setTimeout(function(){button.textContent=original;},1800);}
  if(navigator.clipboard&&window.isSecureContext)navigator.clipboard.writeText(text).then(done);
  else{var area=document.createElement('textarea');area.value=text;area.setAttribute('readonly','');area.style.position='fixed';area.style.opacity='0';document.body.appendChild(area);area.select();document.execCommand('copy');area.remove();done();}
 });
});
})();
