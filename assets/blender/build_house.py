"""Rebuild cutaway GLBs and editable .blend from traced reference geometry.
Run from repository root: blender --background --python assets/blender/build_house.py
Coordinates are metres. Plan tracing is approximate; shell dimensions are user supplied.
"""
import bpy, json, math
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
data=json.loads((ROOT/'resources/js/house.json').read_text())
bpy.ops.object.select_all(action='SELECT');bpy.ops.object.delete(use_global=False)
bpy.context.scene.unit_settings.system='METRIC'
def mat(name,color):
 m=bpy.data.materials.new(name);m.diffuse_color=(*color,1);m.use_nodes=True;m.node_tree.nodes['Principled BSDF'].inputs['Base Color'].default_value=(*color,1);return m
wallmat=mat('Warm lime plaster',(0.77,0.75,0.69));wood=mat('Natural oak',(0.47,0.34,0.21));tile=mat('Warm stone',(0.58,0.59,0.56));edge=mat('Cut wall cap',(0.27,0.29,0.30))
brick=mat('Outer brick',(0.37,0.20,0.13));cavity=mat('Insulated cavity section',(0.33,0.34,0.30))
for f in data['floors']:
 bpy.ops.object.select_all(action='DESELECT');objects=[]
 for room in f['rooms']:
  verts=[(x,y,0) for x,y in room['polygon']];mesh=bpy.data.meshes.new(room['id']);mesh.from_pydata(verts,[],[list(range(len(verts)))]);mesh.update()
  o=bpy.data.objects.new('room__'+room['id'],mesh);bpy.context.collection.objects.link(o);o.data.materials.append(tile if any(s in room['id'] for s in ['wc','bath','utility','laundry','hall','stairs']) else wood)
  solid=o.modifiers.new('Floor thickness','SOLIDIFY');solid.thickness=.15;objects.append(o)
 for i,(x,y,X,Y) in enumerate(f['walls']):
  length=math.hypot(X-x,Y-y)
  # The perimeter coordinates are the supplied INTERNAL wall faces.
  normals=([(0,-1),(-1,0),(-1,0),(-1,0),(0,1),(-1,0),(0,1),(1,0),(1,0),(1,0)] if f['id']=='ground' else [(0,-1),(-1,0),(0,1),(1,0)])
  outside=normals[i] if i<len(normals) else None
  layers=[(.10,.05,wallmat),(.12,.16,cavity),(.10,.27,brick)] if outside else [(.10,0,wallmat)]
  for layer,(thickness,offset,material) in enumerate(layers):
   dx,dy=outside or (0,0)
   bpy.ops.mesh.primitive_cube_add(size=1,location=((x+X)/2+dx*offset,(y+Y)/2+dy*offset,.65))
   o=bpy.context.object;o.name=f"wall__{f['id']}__{i}__{layer}";o.dimensions=(length,thickness,1.3);o.rotation_euler.z=math.atan2(Y-y,X-x)
   bpy.ops.object.transform_apply(location=False,rotation=False,scale=True);o.data.materials.append(material);o['provisional_full_height_m']=2.6;objects.append(o)
 for o in objects:o.select_set(True)
 bpy.ops.export_scene.gltf(filepath=str(ROOT/'public/models'/f"{f['id']}.glb"),export_format='GLB',use_selection=True,export_extras=True)
 for o in objects:o.hide_set(True)
for o in bpy.data.objects:
 if o.name.startswith('wall__ground') or o.name.removeprefix('room__') in [r['id'] for r in data['floors'][0]['rooms']]:o.hide_set(False)
bpy.ops.wm.save_as_mainfile(filepath=str(ROOT/'assets/blender/house.blend'))
