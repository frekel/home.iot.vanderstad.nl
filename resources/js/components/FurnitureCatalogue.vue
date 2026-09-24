<script setup lang="ts">
import {computed,ref,watch,onBeforeUnmount} from 'vue';
import {X} from '@lucide/vue';
import * as THREE from 'three';
import {GLTFLoader} from 'three/addons/loaders/GLTFLoader.js';
import {house} from '../house';
import {furnitureState,furnitureLoadError,refreshFurniture,type FurnitureItem} from '../furniture';
const props=defineProps<{initialFloor:string;roomLabel:(id:string)=>string}>();
defineEmits<{close:[]}>();
const floor=ref(props.initialFloor),selected=ref('');
const prefixes:Record<string,string>={ground:'BG',upper:'V1',attic:'Z'};
const floorNames:Record<string,string>={ground:'Begane grond',upper:'Eerste verdieping',attic:'Zolder'};
const names:Record<string,string>={sofa:'Bank',armchair:'Fauteuil',cabinet:'Kast',corner_sofa:'Hoekbank',split_door_cabinet:'Kast · deuren 60 / 40 cm',open_shelving:'Open plankenkast',dog_house:'Hondenhok',wall_cabinet_set:'Drie hangkastjes van 60 cm',american_fridge:'Amerikaanse koelkast',shoe_rack:'Schoenenrek',breakfast_bar:'Bar op BG-53',wall_shelf:'Wandplank',drawer_cabinet:'Ladekastje',rotating_mirror_cabinet:'Draaikast met spiegel',bed:'Bed',tv:'Tv',chair:'Stoel',fridge:'Koelkast',cooker:'Fornuis',sink:'Spoelbak / wastafel',toilet:'Toilet',bath:'Bad',plant:'Plant',computer:'Computer',table:'Tafel / bureau',washer:'Wasmachine / droger',open_wardrobe:'Open kledingkast · half hangen / half planken',winder_stairs:'Halfslagtrap · 180°',projector_screen:'Beamerscherm',shower:'Douche met halfrond gordijn',water_heater:'Boiler',heating_boiler:'Cv-ketel',infrared_panel:'Infraroodpaneel',mirror:'Wandspiegel',basket_cabinet:'Ladekast met mandjes',trash_bin:'Prullenbak',countertop:'Keukenblad'};
type Item=FurnitureItem;
function reference(i:Item){return `${prefixes[i.floor]}-${i.id==='levi-tv-cabinet'?'901':i.id==='levi-tv'?'902':i.id}`}
function label(i:Item){if(i.kind==='wall_cabinet_set')return `Drie hangkastjes · elk ${Math.round(i.width*100/3)} cm breed`;if(i.kind==='split_door_cabinet')return `Kast · deuren ${Math.round(i.width*60)} / ${Math.round(i.width*40)} cm`;return i.id==='levi-tv-cabinet'?'Kast aan voeteneinde V1-244':i.id==='levi-tv'?'Levi · tv aan muur':names[i.kind]??i.kind}
function contains(p:number[][],x:number,y:number){let inside=false;for(let i=0,j=p.length-1;i<p.length;j=i++){const a=p[i]!,b=p[j]!;if((a[1]!>y)!==(b[1]!>y)&&x<(b[0]!-a[0]!)*(y-a[1]!)/(b[1]!-a[1]!)+a[0]!)inside=!inside}return inside}
function room(i:Item){const r=house.floors.find(f=>f.id===i.floor)?.rooms.find(r=>contains(r.polygon,i.x,i.y));return r?props.roomLabel(r.id):'Plaatsing controleren'}
function position(x:number,y:number){const mirrored=house.floors.find(f=>f.id===floor.value)?.display_mirrored??false;return mirrored?[-x,-y]:[x,y]}
function itemKey(i:Item){return `${i.floor}:${i.id}`}
function databaseFootprint(i:Item){const a=i.rotation*Math.PI/180;return [[-1,-1],[1,-1],[1,1],[-1,1]].map(([sx,sy])=>{const x=sx!*i.width/2,y=sy!*i.depth/2;return position(i.x+x*Math.cos(a)-y*Math.sin(a),i.y+x*Math.sin(a)+y*Math.cos(a)).join(',')}).join(' ')}
const modelFootprints=ref<Record<string,string>>({});
const modelCenters=ref<Record<string,[number,number]>>({});
let footprintRequest=0;
function convexHull(points:[number,number][]):[number,number][]{
 const unique=[...new Map(points.map(p=>[`${p[0].toFixed(5)},${p[1].toFixed(5)}`,p])).values()].sort((a,b)=>a[0]-b[0]||a[1]-b[1]);
 if(unique.length<=2)return unique;
 const cross=(o:[number,number],a:[number,number],b:[number,number])=>(a[0]-o[0])*(b[1]-o[1])-(a[1]-o[1])*(b[0]-o[0]);
 const lower:[number,number][]=[];for(const p of unique){while(lower.length>=2&&cross(lower[lower.length-2]!,lower[lower.length-1]!,p)<=0)lower.pop();lower.push(p)}
 const upper:[number,number][]=[];for(let n=unique.length-1;n>=0;n--){const p=unique[n]!;while(upper.length>=2&&cross(upper[upper.length-2]!,upper[upper.length-1]!,p)<=0)upper.pop();upper.push(p)}
 lower.pop();upper.pop();return [...lower,...upper];
}
function disposeModel(root:THREE.Object3D){root.traverse(o=>{if(o instanceof THREE.Mesh){o.geometry.dispose();for(const material of Array.isArray(o.material)?o.material:[o.material])material.dispose()}})}
async function refreshModelFootprints(){
 const request=++footprintRequest;
 const state=furnitureState.value;
 const modelUrl=state?.models[floor.value];
 if(!state||!modelUrl||state.status!=='ready'||state.model_revision!==state.revision){modelFootprints.value={};modelCenters.value={};return}
 try{
  const gltf=await new GLTFLoader().loadAsync(modelUrl);
  if(request!==footprintRequest){disposeModel(gltf.scene);return}
  const floorData=house.floors.find(f=>f.id===floor.value);
  const mirrored=floorData?.display_mirrored??false;
  gltf.scene.scale.set(mirrored?-1:1,1,mirrored?1:-1);
  gltf.scene.updateMatrixWorld(true);
  const footprints:Record<string,string>={};
  const centers:Record<string,[number,number]>={};
  const point=new THREE.Vector3();
  gltf.scene.traverse(root=>{
   const sourceId=root.userData?.source_id;
   if(sourceId===undefined||sourceId===null||!root.name.startsWith(`furniture__${floor.value}__`))return;
   const points:[number,number][]=[];
   root.traverse(child=>{
    if(!(child instanceof THREE.Mesh))return;
    const attribute=child.geometry.getAttribute('position');
    if(!attribute)return;
    for(let n=0;n<attribute.count;n++){
     point.fromBufferAttribute(attribute,n).applyMatrix4(child.matrixWorld);
     points.push([point.x,point.z]);
    }
   });
   const key=`${floor.value}:${String(sourceId)}`;
   const hull=convexHull(points);
   if(hull.length>=3)footprints[key]=hull.map(p=>p.join(',')).join(' ');
   const center=new THREE.Vector3().setFromMatrixPosition(root.matrixWorld);
   centers[key]=[center.x,center.z];
  });
  modelFootprints.value=footprints;modelCenters.value=centers;
  disposeModel(gltf.scene);
 }catch{if(request===footprintRequest){modelFootprints.value={};modelCenters.value={}}}
}
function footprint(i:Item){return modelFootprints.value[itemKey(i)]??databaseFootprint(i)}
const current=computed(()=>house.floors.find(f=>f.id===floor.value)!);
const items=computed<Item[]>(()=>(furnitureState.value?.items??[]).filter(i=>i.floor===floor.value));
const chosen=computed(()=>items.value.find(i=>reference(i)===selected.value));
function isSelected(i:Item){return selected.value===reference(i)||!!(chosen.value?.group&&i.group===chosen.value.group)}
const markers=computed(()=>items.value.map(i=>{const p=modelCenters.value[itemKey(i)]??position(i.x,i.y);return {item:i,x:p[0]!,y:p[1]!}}));
watch(()=>[floor.value,furnitureState.value?.models[floor.value],furnitureState.value?.revision,furnitureState.value?.model_revision,furnitureState.value?.status],()=>{void refreshModelFootprints()},{immediate:true});
onBeforeUnmount(()=>{footprintRequest++});
function choose(i:Item){selected.value=reference(i);document.getElementById('furniture-'+reference(i))?.scrollIntoView({block:'nearest',behavior:'smooth'})}
function dimensions(i:Item){return [i.width,i.depth,i.height].map(v=>Math.round(v*100)).join(' × ')+' cm'}
type NumericField='width'|'depth'|'height'|'x'|'y'|'base_z';
type Draft=Record<NumericField,string>&{color:string};
const dimensionFields:{key:NumericField;label:string;min:number;max:number}[]=[{key:'width',label:'Breedte',min:5,max:1100},{key:'depth',label:'Diepte',min:1,max:550},{key:'height',label:'Hoogte',min:1,max:400}];
const positionFields:{key:NumericField;label:string;min:number;max:number}[]=[{key:'x',label:'X-as',min:-2000,max:2000},{key:'y',label:'Y-as',min:-2000,max:2000},{key:'base_z',label:'Z-as',min:0,max:500}];
const fields=[...dimensionFields,...positionFields];
const drafts=ref<Record<string,Draft>>({});
const editRevision=ref<number>();
const saving=ref(false),building=ref(false),saveError=ref(''),notice=ref('');
const busy=computed(()=>saving.value||building.value||['queued','building'].includes(furnitureState.value?.status??''));
const changed=computed(()=>(furnitureState.value?.items??[]).filter(i=>drafts.value[reference(i)]));
const modelOutdated=computed(()=>!!furnitureState.value&&furnitureState.value.model_revision!==furnitureState.value.revision);
const buildMessage=computed(()=>{if(building.value)return 'Plattegrond starten…';switch(furnitureState.value?.status){case 'queued':return 'Wachten op opbouw…';case 'building':return 'Plattegrond wordt opgebouwd. Het vorige model blijft zichtbaar.';case 'failed':return 'Opbouwen mislukt. Je gegevens zijn opgeslagen; het vorige model blijft zichtbaar.';default:return notice.value}});
function value(i:Item,key:NumericField){return drafts.value[reference(i)]?.[key]??String(Math.round(i[key]*100000)/1000)}
function ensureDraft(i:Item){const id=reference(i);if(editRevision.value===undefined)editRevision.value=furnitureState.value?.revision;if(!drafts.value[id])drafts.value[id]={...Object.fromEntries(fields.map(f=>[f.key,value(i,f.key)])),color:i.color??''} as Draft;return drafts.value[id]!}
function edit(i:Item,key:NumericField,event:Event){ensureDraft(i)[key]=(event.target as HTMLInputElement).value;saveError.value='';notice.value=''}
function pickerColor(i:Item){return drafts.value[reference(i)]?.color||i.color||'#8b7355'}
function editColor(i:Item,event:Event){ensureDraft(i).color=(event.target as HTMLInputElement).value.toLowerCase();saveError.value='';notice.value=''}
function clearColor(i:Item){ensureDraft(i).color='';saveError.value='';notice.value=''}
function discard(){drafts.value={};editRevision.value=undefined;saveError.value='';notice.value='';void refreshFurniture().catch(()=>{})}
function csrf(){return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content??''}
async function save(){
 if(!furnitureState.value||busy.value||!changed.value.length)return;
 const updates=[];
 for(const item of changed.value){
  const numeric={} as Record<NumericField,number>;
  for(const field of fields){const raw=value(item,field.key),number=Number(raw);if(raw.trim()===''||!Number.isFinite(number)||number<field.min||number>field.max){saveError.value=`${reference(item)}: ${field.label} moet tussen ${field.min} en ${field.max} cm liggen.`;return}numeric[field.key]=number}
  if(numeric.height+numeric.base_z>500){saveError.value=`${reference(item)}: hoogte + Z-as mag maximaal 500 cm zijn.`;return}
  const color=drafts.value[reference(item)]?.color??item.color??'';
  if(color&&!/^#[0-9a-f]{6}$/i.test(color)){saveError.value=`${reference(item)}: ongeldige kleur.`;return}
  updates.push({floor:item.floor,id:item.id,...numeric,color:color||null});
 }
 saving.value=true;saveError.value='';notice.value='';
 try{const response=await fetch('/dashboard/furniture',{method:'PUT',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify({revision:editRevision.value??furnitureState.value.revision,items:updates}),signal:AbortSignal.timeout(20000)});const data=await response.json();if(!response.ok)throw new Error(data.errors?Object.values(data.errors).flat().join(' '):data.message??'Opslaan mislukt.');furnitureState.value=data;drafts.value={};editRevision.value=undefined;notice.value='Afmetingen, positie en kleur opgeslagen. Werk de plattegrond bij wanneer je klaar bent met wijzigen.'}
 catch(error){saveError.value=error instanceof Error?error.message:'Opslaan mislukt. Controleer de verbinding en probeer opnieuw.'}finally{saving.value=false}
}
async function build(){
 if(!furnitureState.value||busy.value||changed.value.length)return;
 building.value=true;saveError.value='';notice.value='';
 try{const response=await fetch('/dashboard/furniture/build',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify({revision:furnitureState.value.revision}),signal:AbortSignal.timeout(20000)});const data=await response.json();if(!response.ok)throw new Error(data.errors?Object.values(data.errors).flat().join(' '):data.message??'Opbouwen starten mislukt.');furnitureState.value=data}
 catch(error){saveError.value=error instanceof Error?error.message:'Opbouwen starten mislukt. Controleer de verbinding en probeer opnieuw.'}finally{building.value=false}
}
</script>
<template>
<div class="modal-backdrop furniture-backdrop" @click.self="!changed.length && $emit('close')"><section class="furniture-catalogue" role="dialog" aria-modal="true" aria-labelledby="furniture-title">
 <button class="icon-button close" aria-label="Meubeloverzicht sluiten" :disabled="!!changed.length" @click="$emit('close')"><X :size="20"/></button>
 <h2 id="furniture-title">Meubels wijzigen</h2><p>Selecteer een meubel, pas afmetingen, positie en kleur aan en sla je wijzigingen op. Werk de 3D-plattegrond pas bij wanneer je klaar bent.</p>
 <div class="furniture-savebar">
 <button class="primary" :disabled="busy || !furnitureState || !changed.length" @click="save">{{saving?'Opslaan…':'Wijzigingen opslaan'}}{{changed.length?' ('+changed.length+')':''}}</button>
 <button :disabled="busy || !furnitureState || !!changed.length || (!modelOutdated && furnitureState.status!=='failed')" @click="build">{{building?'Starten…':furnitureState?.status==='failed'?'Opnieuw opbouwen':'Plattegrond bijwerken'}}</button>
 <button v-if="changed.length" :disabled="busy" @click="discard">Wijzigingen annuleren</button>
 <p role="status" v-if="buildMessage">{{buildMessage}}</p>
 <p role="alert" v-if="saveError || furnitureLoadError">{{saveError || furnitureLoadError}}</p>
 </div>
 <nav class="floor-nav" aria-label="Meubelverdiepingen"><button v-for="f in house.floors" :key="f.id" :class="{active:floor===f.id}" @click="floor=f.id;selected=''">{{floorNames[f.id]}} · {{prefixes[f.id]}}</button></nav>
 <div class="furniture-columns"><div class="furniture-map">
 <svg :viewBox="`${-current.length/2-0.7} -3.6 ${current.length+1.4} 7.2`" role="img" :aria-label="`Meubelkaart ${floorNames[floor]}`">
  <polygon v-for="r in current.rooms" :key="r.id" :points="r.polygon.map(p=>position(p[0]!,p[1]!).join(',')).join(' ')" fill="#242e39" stroke="#46515f" stroke-width=".015"/>
  <line v-for="wall in current.walls" :key="wall.id" :x1="position(wall.x1,wall.y1)[0]" :y1="position(wall.x1,wall.y1)[1]" :x2="position(wall.x2,wall.y2)[0]" :y2="position(wall.x2,wall.y2)[1]" stroke="#bcc5cf" :stroke-width="wall.thickness"/>
  <polygon v-for="i in items" :key="i.id" :points="footprint(i)" :fill="isSelected(i)?'#ff8a1860':'#8b735544'" :stroke="isSelected(i)?'#ffad55':'#aa9375'" stroke-width=".025" @click="choose(i)"><title>{{reference(i)}} · {{label(i)}}</title></polygon>
  <g v-for="m in markers" :key="m.item.id" role="button" tabindex="0" :aria-label="`${reference(m.item)} ${label(m.item)}`" @click="choose(m.item)" @keydown.enter.prevent="choose(m.item)" @keydown.space.prevent="choose(m.item)" style="cursor:pointer"><circle :cx="m.x" :cy="m.y" r=".19" :fill="isSelected(m.item)?'#ff8a18':'#131b24'" stroke="#ffaf59" stroke-width=".017"/><text :x="m.x" :y="m.y+.047" text-anchor="middle" font-size=".135" :fill="isSelected(m.item)?'#101419':'#fff'">{{reference(m.item).split('-')[1]}}</text></g>
 </svg>
 <p>Kaartnummers beginnen met <strong>{{prefixes[floor]}}-</strong>. De kaart volgt de spiegeling van de 3D-plattegrond.</p>
 <div class="furniture-selection" aria-live="polite"><template v-if="chosen"><strong>{{reference(chosen)}} · {{label(chosen)}}</strong><p>{{room(chosen)}} · {{dimensions(chosen)}}</p><p v-if="chosen.group"><strong>{{chosen.group}} · {{chosen.group_name}}</strong> — {{items.filter(i=>i.group===chosen?.group).map(reference).join(', ')}}. Geef dit groepsnummer door om alles samen te wijzigen.</p>
  <p><strong>Afmetingen</strong></p><fieldset class="furniture-fields" :disabled="busy || !furnitureState"><label v-for="field in dimensionFields" :key="field.key">{{field.label}} (cm)<input type="number" step="0.1" :min="field.min" :max="field.max" :value="value(chosen,field.key)" @input="edit(chosen,field.key,$event)"/></label></fieldset>
  <p><strong>Positie</strong> · X/Y zijn databasecoördinaten; Z is de onderkant vanaf de vloer.</p><fieldset class="furniture-fields" :disabled="busy || !furnitureState"><label v-for="field in positionFields" :key="field.key">{{field.label}} (cm)<input type="number" step="0.1" :min="field.min" :max="field.max" :value="value(chosen,field.key)" @input="edit(chosen,field.key,$event)"/></label></fieldset>
  <p><strong>Kleur</strong></p><fieldset class="furniture-fields" :disabled="busy || !furnitureState"><label>Kies kleur<input type="color" :value="pickerColor(chosen)" @input="editColor(chosen,$event)"/></label><label>Huidige keuze<span>{{drafts[reference(chosen)]?.color || chosen.color || 'Standaardkleur'}}</span><button type="button" :disabled="!(drafts[reference(chosen)]?.color || chosen.color)" @click="clearColor(chosen)">Kleur resetten</button></label></fieldset>
  <p>Maten wijzigen rond het middelpunt van het meubel. Hoge meubels kunnen in de plattegrond op muurhoogte worden afgesneden.</p></template><span v-else>Selecteer een meubel voor het volledige nummer.</span></div>
 <p class="furniture-help">Maten: breedte × diepte × hoogte. Positie: X × Y × Z. De kleurkiezer gebruikt de gekozen kleur bij de volgende 3D-opbouw.</p>
 </div><div class="furniture-list"><button v-for="i in items" :key="i.id" :id="'furniture-'+reference(i)" :class="{selected:isSelected(i)}" @click="selected=reference(i)"><span class="furniture-ref">{{reference(i)}}</span><span><strong>{{label(i)}}</strong><small>{{room(i)}} · {{dimensions(i)}}</small><small v-if="i.group">{{i.group}} · {{i.group_name}}</small></span></button></div></div>
</section></div>
</template>