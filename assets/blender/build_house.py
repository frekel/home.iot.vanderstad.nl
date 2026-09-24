"""Rebuild cutaway GLBs from the database-backed floorplan payload.

The worker writes one input JSON containing both `house` geometry and `items`.
No house coordinates are defined or corrected in this renderer.
"""
import bpy, json, math, runpy, hashlib, os, re
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
OUTPUT=Path(os.environ.get('FURNITURE_OUTPUT',str(ROOT/'public/models')))
OUTPUT.mkdir(parents=True,exist_ok=True)

input_path=os.environ.get('FURNITURE_INPUT')
if not input_path:
 raise RuntimeError('FURNITURE_INPUT is required; house geometry must come from the database payload.')
payload=json.loads(Path(input_path).read_text())
data=payload['house']
furniture=payload['items']

bpy.ops.wm.read_factory_settings(use_empty=True)
bpy.context.scene.unit_settings.system='METRIC'
def mat(name,color):
 m=bpy.data.materials.new(name);m.diffuse_color=(*color,1);m.use_nodes=True;m.node_tree.nodes['Principled BSDF'].inputs['Base Color'].default_value=(*color,1);return m
def hex_rgb(value):
 if not isinstance(value,str) or not re.fullmatch(r'#[0-9a-fA-F]{6}',value):
  raise RuntimeError(f'Invalid furniture colour: {value}')
 return tuple(int(value[n:n+2],16)/255 for n in (1,3,5))
wallmat=mat('Warm lime plaster',(0.77,0.75,0.69));wood=mat('Natural oak',(0.47,0.34,0.21));tile=mat('Warm stone',(0.58,0.59,0.56));edge=mat('Cut wall cap',(0.27,0.29,0.30))
brick=mat('Outer brick',(0.37,0.20,0.13));cavity=mat('Insulated cavity section',(0.33,0.34,0.30))
build_furniture=runpy.run_path(str(ROOT/'assets/blender/build_furniture.py'))['build_furniture']
custom_module=runpy.run_path(str(ROOT/'assets/blender/build_custom_furniture.py'))
build_custom_furniture=custom_module['build_custom_furniture'];custom_kinds=custom_module['CUSTOM_KINDS']
palette={'oak':mat('Furniture oak',(.52,.36,.22)),'fabric':mat('Warm grey upholstery',(.22,.25,.25)),'fabric_light':mat('Cushion fabric',(.38,.41,.39)),'linen':mat('Cotton linen',(.86,.84,.76)),'blue':mat('Muted blue bedding',(.21,.35,.43)),'dark':mat('Graphite',(.035,.04,.045)),'screen':mat('TV glass',(.018,.035,.05)),'ceramic':mat('Porcelain',(.88,.88,.83)),'basin':mat('Recessed basin',(.41,.47,.47)),'green':mat('Foliage',(.12,.28,.08)),'basket':mat('Woven baskets',(.48,.37,.24)),'mirror':mat('Mirror glass',(.58,.69,.74)),'stone':tile,'metal':mat('Brushed metal',(.35,.38,.4))}
custom_colour_materials={}

def furniture_colour(value):
 key=value.lower()
 if key not in custom_colour_materials:
  custom_colour_materials[key]=mat('Furniture colour '+key,hex_rgb(key))
 return custom_colour_materials[key]

for f in data['floors']:
 bpy.ops.object.select_all(action='DESELECT');objects=[]
 for room in f['rooms']:
  if not room.get('render_floor',True):
   continue
  verts=[(x,y,0) for x,y in room['polygon']];mesh=bpy.data.meshes.new(room['id']);mesh.from_pydata(verts,[],[list(range(len(verts)))]);mesh.update()
  o=bpy.data.objects.new('room__'+room['id'],mesh);bpy.context.collection.objects.link(o);o.data.materials.append(tile if room.get('floor_material')=='stone' else wood)
  solid=o.modifiers.new('Floor thickness','SOLIDIFY');solid.thickness=.15;objects.append(o)
  stair_item_id=room.get('stair_item_id')
  if stair_item_id:
   stair=next(i for i in furniture if str(i['id'])==str(stair_item_id) and i['floor']==f['id'])
   bpy.ops.mesh.primitive_cube_add(size=1,location=(stair['x'],stair['y'],0))
   bpy.context.object.dimensions=(stair['width']+.03,stair['depth']+.03,1)
   bpy.ops.object.transform_apply(location=False,rotation=False,scale=True)
   cutter=bpy.context.object;opening=o.modifiers.new('Stair opening','BOOLEAN');opening.operation='DIFFERENCE';opening.object=cutter
   bpy.context.view_layer.objects.active=o
   bpy.ops.object.modifier_apply(modifier=solid.name);bpy.ops.object.modifier_apply(modifier=opening.name)
   bpy.data.objects.remove(cutter,do_unlink=True)

 for wall in f['walls']:
  x,y,X,Y=wall['x1'],wall['y1'],wall['x2'],wall['y2']
  length=math.hypot(X-x,Y-y)
  outside=None
  if wall.get('outside_dx') is not None and wall.get('outside_dy') is not None:
   outside=(wall['outside_dx'],wall['outside_dy'])
  layers=[(.10,.05,wallmat),(.12,.16,cavity),(.10,.27,brick)] if outside else [(wall.get('thickness',.10),0,wallmat)]
  for layer,(thickness,offset,material) in enumerate(layers):
   dx,dy=outside or (0,0)
   bpy.ops.mesh.primitive_cube_add(size=1,location=((x+X)/2+dx*offset,(y+Y)/2+dy*offset,.65))
   o=bpy.context.object;o.name=f"wall__{f['id']}__{wall['id']}__{layer}";o.dimensions=(length,thickness,1.3);o.rotation_euler.z=math.atan2(Y-y,X-x)
   bpy.ops.object.transform_apply(location=False,rotation=False,scale=True);o.data.materials.append(material);o['provisional_full_height_m']=2.6;o['wall_name']=wall.get('name') or '';objects.append(o)

 regular=[item for item in furniture if item.get('model_kind') not in custom_kinds]
 objects.extend(build_furniture(regular,f,palette))
 objects.extend(build_custom_furniture(furniture,f,palette))

 # Item-specific appearance is data. A user-defined hex colour takes priority
 # over the generic material palette override.
 for item in furniture:
  if item['floor']!=f['id']:
   continue
  material=None
  if item.get('color'):
   material=furniture_colour(item['color'])
  elif item.get('material_override'):
   material=palette.get(item['material_override'])
   if material is None:
    raise RuntimeError(f"Unknown furniture material override: {item['material_override']}")
  if material is None:
   continue
  prefix=f"furniture__{f['id']}__{item['id']}__"
  for o in objects:
   if o.name.startswith(prefix) and getattr(o,'data',None) is not None and hasattr(o.data,'materials'):
    o.data.materials.clear();o.data.materials.append(material)

 bpy.ops.object.select_all(action='DESELECT')
 for o in objects:o.select_set(True)
 bpy.ops.export_scene.gltf(filepath=str(OUTPUT/f"{f['id']}.glb"),export_format='GLB',use_selection=True,export_extras=True)
 for o in objects:o.hide_set(True)

for o in bpy.data.objects:
 if o.name.startswith('furniture__ground') or o.name.startswith('wall__ground') or o.name.removeprefix('room__') in [r['id'] for r in data['floors'][0]['rooms']]:o.hide_set(False)
versions={f['id']:hashlib.sha256((OUTPUT/f"{f['id']}.glb").read_bytes()).hexdigest()[:12] for f in data['floors']}
if 'FURNITURE_OUTPUT' not in os.environ:
 (ROOT/'resources/js/model-versions.json').write_text(json.dumps(versions,indent=2)+'\n')
if 'FURNITURE_OUTPUT' not in os.environ:bpy.ops.wm.save_as_mainfile(filepath=str(ROOT/'assets/blender/house.blend'))
