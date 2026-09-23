"""Rebuild cutaway GLBs and editable .blend from traced reference geometry.
Run from repository root: blender --background --python assets/blender/build_house.py
Coordinates are metres. Plan tracing is approximate; shell dimensions are user supplied.
"""
import bpy, json, math, runpy, hashlib, os
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
OUTPUT=Path(os.environ.get('FURNITURE_OUTPUT',str(ROOT/'public/models')))
OUTPUT.mkdir(parents=True,exist_ok=True)
data=json.loads((ROOT/'resources/js/house.json').read_text())
# User-measured correction: opposite the first-floor stairs the wall between
# the two bedroom doors is 63 cm wide. Preserve its original centre point.
upper=next(f for f in data['floors'] if f['id']=='upper')
for wall in upper['walls']:
 if all(abs(a-b)<.0001 for a,b in zip(wall,[-.2326,.2292,.1628,.2292])):
  wall[:]=[-.3499,.2292,.2801,.2292]
  break

# User-measured attic geometry. Laundry is exactly 5.50 x 2.20 m on the
# complete right side. Homey room "Zolder" (attic-closet) is 2.30 x 2.00 m
# in the outside top-left corner. Preserve the existing door openings.
attic=next(f for f in data['floors'] if f['id']=='attic')
laundry=next(r for r in attic['rooms'] if r['id']=='laundry')
laundry['polygon']=[[1.8,-2.75],[4,-2.75],[4,2.75],[1.8,2.75]]
storage=next(r for r in attic['rooms'] if r['id']=='attic-closet')
storage['polygon']=[[-4,.75],[-1.7,.75],[-1.7,2.75],[-4,2.75]]
for wall in attic['walls']:
 if abs(wall[0]-1.8579)<1e-4 and abs(wall[2]-1.8579)<1e-4:
  wall[0]=wall[2]=1.8
for wall in attic['walls']:
 if all(abs(a-b)<1e-4 for a,b in zip(wall,[-4,.2248,-2.8679,.2248])):
  wall[:]=[-4,.75,-2.8679,.75]
 elif all(abs(a-b)<1e-4 for a,b in zip(wall,[-1.872,.2248,-1.2002,.2248])):
  wall[:]=[-1.872,.75,-1.7,.75]
 elif abs(wall[0]+1.2942)<1e-4 and abs(wall[2]+1.2942)<1e-4:
  wall[:]=[-1.7,.75,-1.7,2.75]
 elif abs(wall[0]+.209)<1e-4 and abs(wall[1]-.2248)<1e-4 and abs(wall[2]-1.8579)<1e-4:
  wall[2]=1.8

bpy.ops.wm.read_factory_settings(use_empty=True)
bpy.context.scene.unit_settings.system='METRIC'
def mat(name,color):
 m=bpy.data.materials.new(name);m.diffuse_color=(*color,1);m.use_nodes=True;m.node_tree.nodes['Principled BSDF'].inputs['Base Color'].default_value=(*color,1);return m
wallmat=mat('Warm lime plaster',(0.77,0.75,0.69));wood=mat('Natural oak',(0.47,0.34,0.21));tile=mat('Warm stone',(0.58,0.59,0.56));edge=mat('Cut wall cap',(0.27,0.29,0.30))
brick=mat('Outer brick',(0.37,0.20,0.13));cavity=mat('Insulated cavity section',(0.33,0.34,0.30))
furniture=json.loads(Path(os.environ.get('FURNITURE_INPUT',str(ROOT/'assets/blender/furniture.json'))).read_text())['items']
build_furniture=runpy.run_path(str(ROOT/'assets/blender/build_furniture.py'))['build_furniture']
custom_module=runpy.run_path(str(ROOT/'assets/blender/build_custom_furniture.py'))
build_custom_furniture=custom_module['build_custom_furniture'];custom_kinds=custom_module['CUSTOM_KINDS']
palette={'oak':mat('Furniture oak',(.52,.36,.22)),'fabric':mat('Warm grey upholstery',(.22,.25,.25)),'fabric_light':mat('Cushion fabric',(.38,.41,.39)),'linen':mat('Cotton linen',(.86,.84,.76)),'blue':mat('Muted blue bedding',(.21,.35,.43)),'dark':mat('Graphite',(.035,.04,.045)),'screen':mat('TV glass',(.018,.035,.05)),'ceramic':mat('Porcelain',(.88,.88,.83)),'basin':mat('Recessed basin',(.41,.47,.47)),'green':mat('Foliage',(.12,.28,.08)),'basket':mat('Woven baskets',(.48,.37,.24)),'mirror':mat('Mirror glass',(.58,.69,.74)),'stone':tile,'metal':mat('Brushed metal',(.35,.38,.4))}
for f in data['floors']:
 bpy.ops.object.select_all(action='DESELECT');objects=[]
 for room in f['rooms']:
  verts=[(x,y,0) for x,y in room['polygon']];mesh=bpy.data.meshes.new(room['id']);mesh.from_pydata(verts,[],[list(range(len(verts)))]);mesh.update()
  o=bpy.data.objects.new('room__'+room['id'],mesh);bpy.context.collection.objects.link(o);o.data.materials.append(tile if any(s in room['id'] for s in ['wc','bath','utility','laundry','hall','stairs']) else wood)
  solid=o.modifiers.new('Floor thickness','SOLIDIFY');solid.thickness=.15;objects.append(o)
  if f['id'] in ['attic','upper'] and room['id']==f['id']+'-stairs':
   stair=next(i for i in furniture if i['kind']=='winder_stairs' and i['floor']==f['id'])
   bpy.ops.mesh.primitive_cube_add(size=1,location=(stair['x'],stair['y'],0))
   bpy.context.object.dimensions=(stair['width']+.03,stair['depth']+.03,1)
   bpy.ops.object.transform_apply(location=False,rotation=False,scale=True)
   cutter=bpy.context.object;opening=o.modifiers.new('Stair opening','BOOLEAN');opening.operation='DIFFERENCE';opening.object=cutter
   bpy.context.view_layer.objects.active=o
   bpy.ops.object.modifier_apply(modifier=solid.name);bpy.ops.object.modifier_apply(modifier=opening.name)
   bpy.data.objects.remove(cutter,do_unlink=True)
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
 regular=[item for item in furniture if item.get('model_kind') not in custom_kinds]
 objects.extend(build_furniture(regular,f,palette))
 objects.extend(build_custom_furniture(furniture,f,palette))
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
