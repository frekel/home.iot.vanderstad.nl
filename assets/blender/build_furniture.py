"""Simple, editable furniture meshes. Footprints come from furniture.json; materials are provisional."""
import bpy, math

def build_furniture(items, floor, materials):
    objects=[];groups={}
    def room_at(x,y):
        for room in floor['rooms']:
            p=room['polygon'];inside=False;j=len(p)-1
            for i in range(len(p)):
                a,b=p[i],p[j]
                if (a[1]>y)!=(b[1]>y) and x<(b[0]-a[0])*(y-a[1])/(b[1]-a[1])+a[0]:inside=not inside
                j=i
            if inside:return room['id']
        return None
    for item in items:
        if item['floor']!=floor['id']:continue
        root=bpy.data.objects.new('furniture__'+floor['id']+'__'+item['id'],None)
        bpy.context.collection.objects.link(root);root.location=(item['x'],item['y'],item.get('base_z',0));root.rotation_euler.z=math.radians(item['rotation'])
        if item.get('mirror_x'):root.scale.x=-1
        root['kind']=item['kind'];root['source_id']=item['id'];root['measured']=item.get('measured',False)
        if item.get('group'):
            key=item['group']
            if key not in groups:
                group=bpy.data.objects.new('furniture_group__'+key,None)
                bpy.context.collection.objects.link(group);group['group']=key;group['label']=item['group_name']
                groups[key]=group;objects.append(group)
            root.parent=groups[key];root['group']=key
        objects.append(root);room=room_at(item['x'],item['y']);original=item.get('original_dimensions',item);w,d,h=original['width'],original['depth'],original['height']
        def finish(o,name,material):
            o.name=root.name+'__'+name;o.parent=root;o.data.materials.append(materials[material]);o['room']=room or '';objects.append(o)
            return o
        def box(name,size,pos,material='oak',bevel=.015):
            bpy.ops.mesh.primitive_cube_add(size=1);o=bpy.context.object;o.dimensions=size
            bpy.ops.object.transform_apply(location=False,rotation=False,scale=True);o.location=pos
            if bevel:
                mod=o.modifiers.new('Soft edges','BEVEL');mod.width=min(bevel,min(size)/4);mod.segments=2
                o.modifiers.new('Weighted normals','WEIGHTED_NORMAL')
            return finish(o,name,material)
        def cylinder(name,radius,depth,pos,material='dark',rotate=None):
            bpy.ops.mesh.primitive_cylinder_add(vertices=24,radius=radius,depth=depth,location=pos);o=bpy.context.object
            if rotate:o.rotation_euler=rotate
            return finish(o,name,material)
        def legs(height,topw=w,topd=d):
            for x in [-topw/2+.06,topw/2-.06]:
                for y in [-topd/2+.06,topd/2-.06]:box('leg',(.045,.045,height),(x,y,height/2),'dark',.005)
        k=item['kind']
        if k in ['sofa','armchair']:
            box('base',(w,d,.25),(0,0,.23),'fabric')
            count=3 if k=='sofa' else 1
            for i in range(count):box('seat',(max(.08,(w-.18)/count-.015),d-.18,.18),(-w/2+.09+(i+.5)*(w-.18)/count,.025,.43),'fabric_light',.05)
            box('back',(w,.16,h-.23),(0,-d/2+.08,(h+.23)/2),'fabric',.05)
            for side,x in [('left',-w/2+.06),('right',w/2-.06)]:
                if side!=item.get('open_arm'):box('arm',(.12,d,.45),(x,0,.4),'fabric',.04)
        elif k=='corner_sofa':
            seat=item['seat_depth'];arm=item['return_width']
            box('long_base',(w,seat,.25),(0,-d/2+seat/2,.23),'fabric')
            box('return_base',(arm,d-seat,.25),(-w/2+arm/2,seat/2,.23),'fabric')
            box('long_back',(w,.16,h-.23),(0,-d/2+.08,(h+.23)/2),'fabric',.05)
            box('return_back',(.16,d,h-.23),(-w/2+.08,0,(h+.23)/2),'fabric',.05)
            for n in range(4):
                sw=(w-.24)/4
                box('seat', (sw-.014,seat-.18,.18),(-w/2+.12+(n+.5)*sw,-d/2+seat/2+.06,.43),'fabric_light',.05)
            for n in range(2):
                sd=(d-seat-.12)/2
                box('return_seat',(arm-.18,sd-.014,.18),(-w/2+arm/2+.05,-d/2+seat+(n+.5)*sd,.43),'fabric_light',.05)
            box('end_arm',(.12,seat,.45),(w/2-.06,-d/2+seat/2,.4),'fabric',.04)
            box('return_arm',(arm,.12,.45),(-w/2+arm/2,d/2-.06,.4),'fabric',.04)
        elif k=='split_door_cabinet':
            box('body',(w,d,h),(0,0,h/2),'oak')
            # Viewed from the front, viewer-left is positive local X.
            for label,dw,x in [('left',.6,.2),('right',.4,-.3)]:
                box(label+'_door',(dw-.008,.025,h-.025),(x,d/2+.008,h/2),'oak',.004)
            for x in [-.1-.045,-.1+.045]:box('handle',(.025,.025,.15),(x,d/2+.034,h*.55),'metal',.004)
        elif k=='bed':
            box('frame',(w,d,.25),(0,0,.2));box('mattress',(w-.05,d-.05,.2),(0,0,.425),'linen',.045)
            box('blanket',(w-.045,d*.64,.055),(0,d*.14,.55),'blue',.025)
            box('headboard',(w,.065,.88),(0,-d/2+.0325,.44),'oak')
            for x in [-w*.23,w*.23]:box('pillow',(w*.42,d*.19,.1),(x,-d*.33,.565),'linen',.04)
        elif k=='chair':
            legs(.43);box('seat',(w,d,.08),(0,0,.47),'fabric_light',.025);box('back',(w,.07,h-.45),(0,-d/2+.035,(h+.45)/2),'fabric',.025)
        elif k=='table':
            legs(h-.07);box('top',(w,d,.07),(0,0,h-.035),'oak',.025)
        elif k=='tv':
            if 'base_z' not in item:root.location.z=.9
            # XML TV depth represents its plan symbol, not screen thickness.
            box('frame',(w,.055,h),(0,0,h/2),'dark');box('screen',(w-.045,.012,h-.045),(0,.032,h/2),'screen',.005)
            if item.get('standing'):box('stand',(.35,.22,.035),(0,0,-.082),'dark');box('stem',(.045,.045,.1),(0,0,-.04),'dark')
        elif k=='computer':
            root.location.z=item.get('base_z',.78);box('monitor',(w*.8,.035,.33),(0,-d*.2,.22),'dark');box('display',(w*.75,.01,.28),(0,-d*.2+.023,.22),'screen');box('keyboard',(w*.8,d*.3,.025),(0,d*.22,.025),'dark')
        elif k=='shower':
            box('tray',(w,d,.055),(0,0,.0275),'ceramic',.025)
            cylinder('drain',.035,.008,(0,0,.059),'metal')
            radius=w/2-.02;segments=40;verts=[]
            for n in range(segments+1):
                a=n*math.pi/segments;r=radius+.008*math.sin(n*math.pi/2)
                for z in [.09,h-.04]:verts.append((r*math.cos(a),r*math.sin(a),z))
            mesh=bpy.data.meshes.new('curtain');mesh.from_pydata(verts,[],[(2*n,2*n+2,2*n+3,2*n+1) for n in range(segments)]);mesh.update()
            o=bpy.data.objects.new('curtain',mesh);bpy.context.collection.objects.link(o);finish(o,'curtain','linen')
            solid=o.modifiers.new('Curtain thickness','SOLIDIFY');solid.thickness=.003
            curve=bpy.data.curves.new('curtain_rail','CURVE');curve.dimensions='3D';curve.bevel_depth=.012;curve.bevel_resolution=2
            spline=curve.splines.new('POLY');spline.points.add(segments+2)
            coords=[(radius,-d/2,h)]+[(radius*math.cos(n*math.pi/segments),radius*math.sin(n*math.pi/segments),h) for n in range(segments+1)]+[(-radius,-d/2,h)]
            for point,co in zip(spline.points,coords):point.co=(*co,1)
            o=bpy.data.objects.new(root.name+'__rail',curve);bpy.context.collection.objects.link(o);o.parent=root;o.data.materials.append(materials['metal']);objects.append(o)
            cylinder('shower_pipe',.013,1.65,(0,-d/2+.025,.9),'metal')
            box('shower_head',(.15,.12,.03),(0,-d/2+.08,1.75),'metal',.02)
        elif k=='water_heater':
            cylinder('tank',w/2,h,(0,0,h/2),'ceramic')
            cylinder('top',w/2-.008,.025,(0,0,h),'metal')
            box('control',(.11,.018,.08),(0,d/2,.55),'dark',.01)
            for x in [-.08,.08]:cylinder('pipe',.014,.12,(x,0,-.05),'metal')
        elif k=='heating_boiler':
            box('housing',(w,d,h),(0,0,h/2),'ceramic',.025)
            box('controls',(.17,.014,.09),(0,d/2+.007,h*.3),'dark',.007)
            for x in [-.14,-.05,.05,.14]:cylinder('pipe',.012,.25,(x,0,-.12),'metal')
        elif k=='infrared_panel':
            box('panel',(w,d,h),(0,0,h/2),'ceramic',.012)
            box('heating_face',(w-.03,.006,h-.03),(0,d/2+.003,h/2),'linen',.008)
            box('control',(.025,.006,.025),(w/2-.04,d/2+.008,.04),'dark',.003)
        elif k=='wall_cabinet_set':
            for n in range(3):
                x=(n-1)*.6
                box('cabinet_'+str(n+1),(.594,d,h),(x,0,h/2),'oak',.008)
                box('door_'+str(n+1),(.58,.02,h-.025),(x,d/2+.005,h/2),'oak',.004)
                box('handle_'+str(n+1),(.10,.025,.018),(x,d/2+.025,.06),'metal',.003)
        elif k=='american_fridge':
            box('body',(w,d,h),(0,0,h/2),'metal',.025)
            for x in [-w/4,w/4]:
                box('door',(w/2-.012,.035,h-.035),(x,d/2+.01,h/2),'metal',.01)
                box('handle',(.025,.04,h*.3),(x*.18,d/2+.047,h*.56),'dark',.005)
            box('dispenser',(.18,.015,.27),(-w/4,d/2+.035,h*.56),'dark',.006)
        elif k=='open_shelving':
            panel=.02
            box('back',(w,panel,h),(0,-d/2+panel/2,h/2),'oak',.003)
            for x in [-w/2+panel/2,w/2-panel/2]:box('side',(panel,d,h),(x,0,h/2),'oak',.003)
            for n in range(7):box('shelf',(w,d,panel),(0,0,panel/2+n*(h-panel)/6),'oak',.003)
        elif k=='dog_house':
            wall_h=h*.66;panel=.035
            box('floor',(w,d,panel),(0,0,panel/2),'oak')
            box('back',(w,panel,wall_h),(0,-d/2+panel/2,wall_h/2),'oak')
            for x in [-w/2+panel/2,w/2-panel/2]:box('side',(panel,d,wall_h),(x,0,wall_h/2),'oak')
            for x in [-w*.365,w*.365]:box('door_side',(w*.27,panel,wall_h),(x,d/2-panel/2,wall_h/2),'oak')
            box('door_lintel',(w,panel,wall_h*.18),(0,d/2-panel/2,wall_h*.91),'oak')
            verts=[]
            for y in [-d/2-.04,d/2+.04]:verts.extend([(-w/2-.035,y,wall_h),(w/2+.035,y,wall_h),(0,y,h)])
            mesh=bpy.data.meshes.new('kennel_roof');mesh.from_pydata(verts,[],[(0,2,1),(3,4,5),(0,3,5,2),(2,5,4,1)]);mesh.update()
            o=bpy.data.objects.new('roof',mesh);bpy.context.collection.objects.link(o);finish(o,'pitched_roof','dark')
        elif k=='shoe_rack':
            for x in [-w/2+.018,w/2-.018]:box('side',(.035,d,h),(x,0,h/2),'oak',.005)
            for z in [.055,h/3,2*h/3,h-.025]:
                for n in range(5):box('slat',(w-.07,d/7,.025),(0,-d/2+(n+.5)*d/5,z),'oak',.004)
            for z in [h/3,2*h/3]:
                for n in range(4):box('shoe',(.12,d*.48,.075),(-w/2+.18+n*(w-.36)/3,0,z+.05),'dark',.025)
        elif k=='breakfast_bar':
            thickness=.04;support=item['support_height'];post_h=h-thickness-support
            box('top',(w,d,thickness),(0,0,h-thickness/2),'oak',.01)
            for y in [-d*.32,d*.32]:cylinder('counter_support',.022,post_h,(-w/2+.3,y,support+post_h/2),'metal')
            cylinder('outer_pole',.035,h-thickness,(w/2-.08,0,(h-thickness)/2),'metal')
        elif k=='wall_shelf':
            box('shelf',(w,d,h),(0,0,h/2),'oak',.006)
        elif k=='drawer_cabinet':
            box('body',(w,d,h),(0,0,h/2))
            for n in range(3):
                z=(n+.5)*h/3
                box('drawer',(w-.025,.025,h/3-.018),(0,d/2+.006,z),'oak',.005)
                box('handle',(.12,.025,.022),(0,d/2+.032,z),'metal',.004)
        elif k=='rotating_mirror_cabinet':
            cylinder('swivel_base',min(w,d)*.4,.045,(0,0,.0225),'metal')
            box('body',(w,d,h-.045),(0,0,(h+.045)/2))
            box('mirror',(w-.055,.008,h-.105),(0,d/2+.006,h/2+.015),'mirror',.003)
        elif k=='mirror':
            box('frame',(w,d,h),(0,0,h/2),'oak',.007)
            box('mirror',(w-.04,.008,h-.04),(0,d/2+.004,h/2),'mirror',.002)
        elif k=='basket_cabinet':
            panel=.025
            for x in [-w/2+panel/2,0,w/2-panel/2]:box('side',(panel,d,h),(x,0,h/2),'oak',.004)
            box('back',(w,.02,h),(0,-d/2+.01,h/2),'oak',.004)
            for row in range(4):box('shelf',(w,d,panel),(0,0,panel/2+row*(h-panel)/3),'oak',.004)
            for row in range(3):
                z=(row+.5)*(h-panel)/3+.015
                for col in [-1,1]:
                    x=col*w/4;bw=w/2-.05;bh=(h-panel)/3-.04
                    box('basket',(bw,d-.04,bh),(x,.01,z),'basket',.012)
                    for n in range(4):box('woven_strip',(bw,.008,.009),(x,d/2-.004,z-bh/2+.025+n*(bh-.05)/3),'oak',.002)
                    box('handle',(.09,.012,.032),(x,d/2+.003,z+bh*.24),'dark',.009)
        elif k=='projector_screen':
            box('projection_fabric',(w,.015,h),(0,0,h/2),'linen',.001)
            box('top_casing',(w+.025,.04,.035),(0,0,h+.0175),'ceramic',.008)
            box('bottom_bar',(w,.025,.022),(0,0,.011),'dark',.004)
        elif k=='winder_stairs':
            # Cutaway display: deep treads otherwise disappear behind the floor rim.
            root['full_descent_m']=h;root['cutaway_vertical_scale']=.2;root.scale.z=.2
            # Two parallel end flights joined by a full 180-degree fan, per plan.
            straight=2;turning=12;steps=2*straight+turning;rise=h/steps
            leg=d*.28;pivot_y=-d/2+leg;fan_depth=d-leg
            corner=math.atan2(fan_depth,w/2)
            def rim(a):
                limits=[]
                if abs(math.cos(a))>1e-8:limits.append(w/2/abs(math.cos(a)))
                if math.sin(a)>1e-8:limits.append(fan_depth/math.sin(a))
                r=min(limits)
                return (r*math.cos(a),pivot_y+r*math.sin(a))
            polygons=[];outer=[]
            for n in range(straight):
                y0=-d/2+n*leg/straight;y1=y0+leg/straight
                polygons.append([(0,y0),(w/2,y0),(w/2,y1),(0,y1)])
                outer.append([(w/2,y0),(w/2,y1)])
            for n in range(turning):
                start=n*math.pi/turning;end=(n+1)*math.pi/turning
                angles=[start]+[a for a in [corner,math.pi-corner] if start<a<end]+[end]
                polygons.append([(0,pivot_y)]+[rim(a) for a in angles]);outer.append([rim(a) for a in angles])
            for n in range(straight):
                y1=pivot_y-n*leg/straight;y0=y1-leg/straight
                polygons.append([(-w/2,y0),(0,y0),(0,y1),(-w/2,y1)])
                outer.append([(-w/2,y1),(-w/2,y0)])
            rail=[]
            for n,(polygon,edge_points) in enumerate(zip(polygons,outer)):
                z=-.035-n*rise;count=len(polygon)
                verts=[(x,y,level) for level in [z-.055,z] for x,y in polygon]
                faces=[tuple(reversed(range(count))),tuple(range(count,2*count))]
                faces += [(i,(i+1)%count,(i+1)%count+count,i+count) for i in range(count)]
                mesh=bpy.data.meshes.new('half_turn_tread');mesh.from_pydata(verts,[],faces);mesh.update()
                o=bpy.data.objects.new('tread',mesh);bpy.context.collection.objects.link(o);finish(o,'tread_'+str(n+1),'oak')
                px,py=edge_points[-1]
                cylinder('baluster',.012,.9,(px,py,z+.45),'dark')
                rail.extend((x,y,z+.9) for x,y in edge_points)
            curve=bpy.data.curves.new('winder_handrail','CURVE');curve.dimensions='3D';curve.bevel_depth=.022;curve.bevel_resolution=2
            spline=curve.splines.new('POLY');spline.points.add(len(rail)-1)
            for point,co in zip(spline.points,rail):point.co=(*co,1)
            o=bpy.data.objects.new(root.name+'__handrail',curve);bpy.context.collection.objects.link(o);o.parent=root;o.data.materials.append(materials['dark']);objects.append(o)
        elif k=='open_wardrobe':
            panel=.025
            box('back',(w,panel,h),(0,-d/2+panel/2,h/2),'oak',.004)
            for x in [-w/2+panel/2,0,w/2-panel/2]:box('side',(panel,d,h),(x,0,h/2),'oak',.004)
            for z in [panel/2,h-panel/2]:box('horizontal',(w,d,panel),(0,0,z),'oak',.004)
            # After the attic reflection this is the left half when facing the open front.
            cylinder('hanging_rail',.015,w/2-.06,(-w/4,0,h-.3),'metal',(0,math.pi/2,0))
            for n in range(5):
                x=-w/2+.14+n*(w/2-.28)/4
                box('hanging_clothes',(.065,.36,.82),(x,0,h-.79),'blue' if n%2 else 'fabric_light',.015)
            for n in range(1,6):box('shelf',(w/2-panel,d-.025,panel),(w/4,.012,n*h/6),'oak',.004)
        elif k=='plant':
            cylinder('pot',min(w,d)*.26,.35,(0,0,.175),'ceramic')
            for x,y,z in [(0,0,.7),(-w*.16,0,.58),(w*.17,d*.12,.83)]:
                bpy.ops.mesh.primitive_uv_sphere_add(segments=16,ring_count=8,radius=1,location=(x,y,z));o=bpy.context.object;o.scale=(w*.28,d*.28,.3);finish(o,'leaves','green')
        elif k=='toilet':
            box('pedestal',(w*.55,d*.6,.35),(0,d*.08,.175),'ceramic',.08);box('bowl',(w,d*.75,.16),(0,d*.12,.43),'ceramic',.07);box('cistern',(w,d*.2,h),(0,-d*.4,h/2),'ceramic',.04)
        elif k=='bath':
            box('tub',(w,d,h),(0,0,h/2),'ceramic',.08);box('basin',(w*.78,d*.7,.04),(0,0,h+.005),'basin',.08)
        else:
            material='ceramic' if k in ['fridge','washer'] else 'oak'
            box('body',(w,d,h),(0,0,h/2),material)
            if k=='sink':box('counter',(w+.01,d+.01,.035),(0,0,h),'stone');box('basin',(w*.65,d*.58,.045),(0,0,h+.02),'basin');cylinder('tap',.022,.25,(0,-d*.32,h+.13),'metal')
            elif k=='cooker':
                box('hob',(w*.94,d*.94,.025),(0,0,h+.015),'dark')
                for x in [-w*.23,w*.23]:
                    for y in [-d*.23,d*.23]:cylinder('burner',min(w,d)*.15,.009,(x,y,h+.032),'metal')
            elif k=='washer':cylinder('door',min(w,h)*.31,.035,(0,d/2+.02,h*.5),'dark',(math.pi/2,0,0))
            else:
                for x in [-w*.25,w*.25]:box('handle',(.035,.025,.12),(x,d/2+.015,h*.65),'metal',.005)
        # Resize the complete furniture, including custom parts, consistently.
        root.scale.x*=item['width']/w;root.scale.y*=item['depth']/d;root.scale.z*=item['height']/h
    return objects
