<script setup lang="ts">
defineProps<{
 devices: {id:string;name:string;zone_name?:string;available:boolean|null;measurements:Record<string,{value:unknown;settable:boolean}>}[];
 connected:boolean;
 busy:Record<string,boolean>;
 pending:Record<string,unknown>;
 errors:Record<string,string>;
}>();
defineEmits<{toggle:[id:string]}>();
</script>
<template>
<div class="light-devices">
 <p v-if="!connected" role="status">Homey is offline. Controls will return when it reconnects.</p>
 <p v-else-if="!devices.length">No Homey lights in this room.</p>
 <article v-for="device in devices" :key="device.id" class="light-device">
  <div><strong>{{device.name}}</strong><small>{{device.zone_name || 'No room'}} · {{device.available!==true ? 'Unavailable' : typeof device.measurements.onoff?.value!=='boolean' ? 'Unknown state' : device.measurements.onoff.value ? 'On' : 'Off'}}</small><small v-if="device.measurements.onoff?.settable!==true">On/off control not supported</small>
   <small role="status" v-if="errors[device.id] || busy[device.id] || pending[device.id]">{{errors[device.id] || (busy[device.id] ? 'Sending…' : 'Waiting for Homey…')}}</small>
  </div>
  <button class="toggle" role="switch" :class="{on:connected && device.available===true && device.measurements.onoff?.value===true}" :aria-checked="connected && device.available===true && device.measurements.onoff?.value===true" :aria-label="`Light: ${device.name}`" :disabled="!connected || device.available!==true || device.measurements.onoff?.settable!==true || typeof device.measurements.onoff?.value!=='boolean' || busy[device.id] || !!pending[device.id]" @click="$emit('toggle',device.id)"><span/></button>
 </article>
</div>
</template>
