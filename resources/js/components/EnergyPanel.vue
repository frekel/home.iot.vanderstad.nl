<script setup lang="ts">
import {computed} from 'vue';
import {Zap} from '@lucide/vue';
type Totals={consumed:number|null;imported:number|null;exported:number|null;generated:number|null;gas:number|null;water:number|null};
export type Energy={available:boolean;date:string;week:string;timezone:string;today:Totals|null;days:Record<string,Totals>;updated_at:string|null};
const props=defineProps<{energy:Energy|null;compact?:boolean}>();
const format=(v:number|null|undefined)=>typeof v==='number'?v.toLocaleString('nl-NL',{minimumFractionDigits:1,maximumFractionDigits:2}):'—';
const days=computed(()=>Object.entries(props.energy?.days??{}).sort(([a],[b])=>a.localeCompare(b)).map(([date,totals])=>({date,value:totals.consumed,label:new Date(date+'T12:00:00+02:00').toLocaleDateString('nl-NL',{weekday:'short',timeZone:'Europe/Amsterdam'}).replace('.','')})));
const max=computed(()=>Math.max(1,...days.value.map(d=>d.value??0)));
const updated=computed(()=>props.energy?.updated_at?new Date(props.energy.updated_at).toLocaleTimeString('nl-NL',{hour:'2-digit',minute:'2-digit',timeZone:'Europe/Amsterdam'}):'');
</script>
<template>
<section :class="compact?'side-section daily-energy':'metric energy-chart'">
 <div class="section-title"><Zap :size="15"/><span>{{compact?'DAILY ENERGY':'Energieverbruik'}}</span><small>Homey</small></div>
 <template v-if="compact">
  <div class="energy-total">{{format(energy?.today?.consumed)}} <span>kWh</span><small>Vandaag</small></div>
  <dl v-if="energy?.available" class="energy-totals">
   <div><dt>Netafname</dt><dd>{{format(energy.today?.imported)}} kWh</dd></div>
   <div><dt>Teruglevering</dt><dd>{{format(energy.today?.exported)}} kWh</dd></div>
   <div><dt>Zonne-opwek</dt><dd>{{format(energy.today?.generated)}} kWh</dd></div>
   <div><dt>Gas</dt><dd>{{format(energy.today?.gas)}} m³</dd></div>
   <div><dt>Water</dt><dd>{{format(energy.today?.water)}} m³</dd></div>
  </dl>
 </template>
 <p v-if="!energy?.available" class="energy-caption" role="status">{{energy===null?'Energy ophalen…':'Homey Energy tijdelijk niet beschikbaar.'}}</p>
 <div v-if="days.length" :class="compact?'mini-bars':'week-chart'" aria-label="Energieverbruik deze week in kWh">
  <div v-for="day in days" :key="day.date" :title="`${day.date}: ${format(day.value)} kWh`"><span>{{format(day.value)}}</span><i :style="{height:`${(day.value??0)/max*(compact?42:75)}px`}" :class="{highlight:day.date===energy?.date}"/><small>{{day.label}}</small></div>
 </div>
 <p v-else-if="energy?.available" class="energy-caption">Weekhistorie niet beschikbaar.</p>
 <p class="energy-caption energy-updated" v-if="energy?.available">Deze week · {{compact?'kWh · ':''}}vandaag tot nu toe · {{updated}}</p>
</section>
</template>
