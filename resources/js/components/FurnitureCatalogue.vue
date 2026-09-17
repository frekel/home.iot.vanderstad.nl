<script setup lang="ts">
import {computed,ref} from 'vue';
import {X} from '@lucide/vue';
import house from '../house.json';
import {furnitureState,furnitureLoadError,refreshFurniture,type FurnitureItem} from '../furniture';
const props=defineProps<{initialFloor:string;roomLabel:(id:string)=>string}>();
defineEmits<{close:[]}>();
const floor=ref(props.initialFloor),selected=ref('');
const prefixes:Record<string,string>={ground:'BG',upper:'V1',attic:'Z'};
const floorNames:Record<string,string>={ground:'Begane grond',upper:'Eerste verdieping',attic:'Zolder'};
const names:Record<string,string>={sofa:'Bank',armchair:'Fauteuil',cabinet:'Kast',corner_sofa:'Hoekbank',split_door_cabinet:'Kast · deuren 60 / 40 cm',open_shelving:'Open plankenkast',dog_house:'Hondenhok',wall_cabinet_set:'Drie hangkastjes van 60 cm',american_fridge:'Amerikaanse koelkast',shoe_rack:'Schoenenrek',breakfast_bar:'Bar op BG-53',wall_shelf:'Wandplank',drawer_cabinet:'Ladekastje',rotating_mirror_cabinet:'Draaikast met spiegel',bed:'Bed',tv:'Tv',chair:'Stoel',fridge:'Koelkast',cooker:'Fornuis',sink:'Spoelbak / wastafel',toilet:'Toilet',bath:'Bad',plant:'Plant',computer:'Computer',table:'Tafel / bureau',washer:'Wasmachine / droger',open_wardrobe:'Open kledingkast · half hangen / half planken',winder_stairs:'Halfslagtrap · 180°',projector_screen:'Beamerscherm',shower:'Douche met halfrond gordijn',water_heater:'Boiler',heating_boiler:'Cv-ketel',infrared_panel:'Infraroodpaneel',mirror:'Wandspiegel',basket_cabinet:'Ladekast met mandjes'};
type Item=FurnitureItem;
function reference(i:Item){return `${prefixes[i.floor]}-${i.id==='levi-tv-cabinet'?'901':i.id==='levi-tv'?'902':i.id}`}
function label(i:Item){if(i.kind==='wall_cabinet_set')return `Drie hangkastjes · elk ${Math.round(i.width*100/3)} cm breed`;if(i.kind==='split_door_cabinet')return `Kast · deuren ${Math.round(i.width*60)} / ${Math.round(i.width*40)} cm`;return i.id==='levi-tv-cabinet'?'Kast aan voeteneinde V1-244':i.id==='levi-tv'?'Levi · tv aan muur':names[i.kind]??i.kind}
function contains(p:number[][],x:number,y:number){let inside=false;for(let i=0,j=p.length-1;i<p.length;j=i++){const a=p[i]!,b=p[j]!;if((a[1]!>y)!==(b[1]!>y)&&x<(b[0]!-a[0]!)*(y-a[1]!)/(b[1]!-a[1]!)+a[0]!)inside=!inside}return inside}
function room(i:Item){const r=house.floors.find(f=>f.id===i.floor)?.rooms.find(r=>contains(r.polygon,i.x,i.y));return r?props.roomLabel(r.id):'Plaatsing controleren'}
function position(x:number,y:number){return [floor.value==='ground'?x:-x,floor.value==='ground'?y:-y]}
function footprint(i:Item){const a=i.rotation*Math.PI/180;return [[-1,-1],[1,-1],[1,1],[-1,1]].map(([sx,sy])=>{const x=sx!*i.width/2,y=sy!*i.depth/2;return position(i.x+x*Math.cos(a)-y*Math.sin(a),i.y+x*Math.sin(a)+y*Math.cos(a)).join(',')}).join(' ')}
const current=computed(()=>house.floors.find(f=>f.id===floor.value)!);
const items=computed<Item[]>(()=>(furnitureState.value?.items??[]).filter(i=>i.floor===floor.value));
const chosen=computed(()=>items.value.find(i=>reference(i)===selected.value));
function isSelected(i:Item){return selected.value===reference(i)||!!(chosen.value?.group&&i.group===chosen.value.group)}
const markers=computed(()=>{const used:number[][]=[];return items.value.map(i=>{const p=position(i.x,i.y);while(used.some(u=>Math.hypot(u[0]!-p[0]!,u[1]!-p[1]!)<.36)){p[0]!+=.3;p[1]!-=.25}used.push([...p]);return {item:i,x:p[0]!,y:p[1]!}})});
function choose(i:Item){selected.value=reference(i);document.getElementById('furniture-'+reference(i))?.scrollIntoView({block:'nearest',behavior:'smooth'})}
function dimensions(i:Item){return [i.width,i.depth,i.height].map(v=>Math.round(v*100)).join(' × ')+' cm'}
type Dimension='width'|'depth'|'height'|'base_z';
const fields:{key:Dimension;label:string;min:number;max:number}[]=[{key:'width',label:'Breedte',min:5,max:1100},{key:'depth',label:'Diepte',min:1,max:550},{key:'height',label:'Hoogte',min:1,max:400},{key:'base_z',label:'Onderkant vanaf vloer',min:0,max:300}];
const drafts=ref<Record<string,Record<Dimension,string>>>({});
const editRevision=ref<number>();
const saving=ref(false),building=ref(false),saveError=ref(''),notice=ref('');
const busy=computed(()=>saving.value||building.value||['queued','building'].includes(furnitureState.value?.status??''));
const changed=computed(()=>(furnitureState.value?.items??[]).filter(i=>drafts.value[reference(i)]));
const modelOutdated=computed(()=>!!furnitureState.value&&furnitureState.value.model_revision!==furnitureState.value.revision);
const buildMessage=computed(()=>{if(building.value)return 'Plattegrond starten…';switch(furnitureState.value?.status){case 'queued':return 'Wachten op opbouw…';case 'building':return 'Plattegrond wordt opgebouwd. Het vorige model blijft zichtbaar.';case 'failed':return 'Opbouwen mislukt. Je maten zijn opgeslagen; het vorige model blijft zichtbaar.';default:return notice.value}});
function value(i:Item,key:Dimension){return drafts.value[reference(i)]?.[key]??String(Math.round(i[key]*100000)/1000)}
function edit(i:Item,key:Dimension,event:Event){if(editRevision.value===undefined)editRevision.value=furnitureState.value?.revision;const id=reference(i);drafts.value[id]??=Object.fromEntries(fields.map(f=>[f.key,value(i,f.key)])) as Record<Dimension,string>;drafts.value[id][key]=(event.target as HTMLInputElement).value;saveError.value='';notice.value=''}
function discard(){drafts.value={};editRevision.value=undefined;saveError.value='';notice.value='';void refreshFurniture().catch(()=>{})}
function csrf(){return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content??''}
async function save(){
 if(!furnitureState.value||busy.value||!changed.value.length)return;
 const updates=[];
 for(const item of changed.value){const dimensions={} as Record<Dimension,number>;for(const field of fields){const raw=value(item,field.key),number=Number(raw);if(raw.trim()===''||!Number.isFinite(number)||number<field.min||number>field.max){saveError.value=`${reference(item)}: ${field.label} moet tussen ${field.min} en ${field.max} cm liggen.`;return}dimensions[field.key]=number}updates.push({floor:item.floor,id:item.id,...dimensions})}
 saving.value=true;saveError.value='';notice.value='';
 try{const response=await fetch('/dashboard/furniture',{method:'PUT',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify({revision:editRevision.value??furnitureState.value.revision,items:updates}),signal:AbortSignal.timeout(20000)});const data=await response.json();if(!response.ok)throw new Error(data.errors?Object.values(data.errors).flat().join(' '):data.message??'Opslaan mislukt.');furnitureState.value=data;drafts.value={};editRevision.value=undefined;notice.value='Maten opgeslagen. Werk de plattegrond bij wanneer je klaar bent met wijzigen.'}
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
 <h2 id="furniture-title">Meubels wijzigen</h2><p>Selecteer een meubel, pas de maten in centimeters aan en sla je wijzigingen op. Werk de 3D-plattegrond pas bij wanneer je klaar bent.</p>
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
  <line v-for="(wall,n) in current.walls" :key="n" :x1="position(wall[0]!,wall[1]!)[0]" :y1="position(wall[0]!,wall[1]!)[1]" :x2="position(wall[2]!,wall[3]!)[0]" :y2="position(wall[2]!,wall[3]!)[1]" stroke="#bcc5cf" stroke-width=".065"/>
  <polygon v-for="i in items" :key="i.id" :points="footprint(i)" :fill="isSelected(i)?'#ff8a1860':'#8b735544'" :stroke="isSelected(i)?'#ffad55':'#aa9375'" stroke-width=".025" @click="choose(i)"><title>{{reference(i)}} · {{label(i)}}</title></polygon>
  <g v-for="m in markers" :key="m.item.id" role="button" tabindex="0" :aria-label="`${reference(m.item)} ${label(m.item)}`" @click="choose(m.item)" @keydown.enter.prevent="choose(m.item)" @keydown.space.prevent="choose(m.item)" style="cursor:pointer"><line :x1="position(m.item.x,m.item.y)[0]" :y1="position(m.item.x,m.item.y)[1]" :x2="m.x" :y2="m.y" stroke="#dfaf70" stroke-width=".018"/><circle :cx="m.x" :cy="m.y" r=".19" :fill="isSelected(m.item)?'#ff8a18':'#131b24'" stroke="#ffaf59" stroke-width=".017"/><text :x="m.x" :y="m.y+.047" text-anchor="middle" font-size=".135" :fill="isSelected(m.item)?'#101419':'#fff'">{{reference(m.item).split('-')[1]}}</text></g>
 </svg>
 <p>Kaartnummers beginnen met <strong>{{prefixes[floor]}}-</strong>. De kaart volgt de spiegeling van de 3D-plattegrond.</p>
 <div class="furniture-selection" aria-live="polite"><template v-if="chosen"><strong>{{reference(chosen)}} · {{label(chosen)}}</strong><p>{{room(chosen)}} · {{dimensions(chosen)}}</p><p v-if="chosen.group"><strong>{{chosen.group}} · {{chosen.group_name}}</strong> — {{items.filter(i=>i.group===chosen?.group).map(reference).join(', ')}}. Geef dit groepsnummer door om alles samen te wijzigen.</p><fieldset class="furniture-fields" :disabled="busy || !furnitureState"><label v-for="field in fields" :key="field.key">{{field.label}} (cm)<input type="number" step="0.1" :min="field.min" :max="field.max" :value="value(chosen,field.key)" @input="edit(chosen,field.key,$event)"/></label></fieldset><p>Maten wijzigen rond het middelpunt van het meubel. Hoge meubels kunnen in de plattegrond op muurhoogte worden afgesneden.</p><p>Geef bijvoorbeeld door: “{{reference(chosen)}} 20 cm naar rechts op de meubelkaart.”</p></template><span v-else>Selecteer een meubel voor het volledige nummer.</span></div>
 <p class="furniture-help">Maten: breedte × diepte × hoogte. Doorgegeven maten zijn verwerkt; overige maten en meubeltypen zijn benaderingen uit de tekening. Gebruik voor verplaatsen een afstand in cm en een muur, raam of richting op deze kaart.</p>
 </div><div class="furniture-list"><button v-for="i in items" :key="i.id" :id="'furniture-'+reference(i)" :class="{selected:isSelected(i)}" @click="selected=reference(i)"><span class="furniture-ref">{{reference(i)}}</span><span><strong>{{label(i)}}</strong><small>{{room(i)}} · {{dimensions(i)}}</small><small v-if="i.group">{{i.group}} · {{i.group_name}}</small></span></button></div></div>
</section></div>
</template>
