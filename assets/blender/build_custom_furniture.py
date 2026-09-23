"""Custom furniture shapes for user-measured pieces that are not in the traced source."""
import bpy, math

CUSTOM_KINDS={'hanging_desk','monitor','laptop','wardrobe_drawers','micke_desk','kobra4','skadis_filament','storage_rack','laundry_basket','wall_radiator'}

def build_custom_furniture(items, floor, materials):
    objects=[]

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
        if item['floor']!=floor['id'] or item.get('model_kind') not in CUSTOM_KINDS:continue
        root=bpy.data.objects.new('furniture__'+floor['id']+'__'+item['id'],None)
        bpy.context.collection.objects.link(root)
        root.location=(item['x'],item['y'],item.get('base_z',0))
        root.rotation_euler.z=math.radians(item.get('rotation',0))
        root['kind']=item['kind'];root['source_id']=item['id'];root['measured']=item.get('measured',False)
        objects.append(root)
        room=room_at(item['x'],item['y'])
        w,d,h=item['width'],item['depth'],item['height']

        def finish(o,name,material):
            o.name=root.name+'__'+name;o.parent=root;o.data.materials.append(materials[material]);o['room']=room or '';objects.append(o)
            return o

        def box(name,size,pos,material='oak',bevel=.01):
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

        k=item['model_kind']
        if k=='hanging_desk':
            box('desktop',(w,d,h),(0,0,h/2),'oak',.008)

        elif k=='monitor':
            screen_h=h*.78
            box('frame',(w,.045,screen_h),(0,-d*.18,screen_h/2+.055),'dark',.008)
            box('screen',(w-.035,.008,screen_h-.035),(0,-d*.18+.027,screen_h/2+.055),'screen',.003)
            box('stem',(.04,.04,.08),(0,-d*.05,.04),'dark',.004)
            box('foot',(w*.28,d*.55,.025),(0,0,.0125),'dark',.005)

        elif k=='laptop':
            base_h=.025
            box('base',(w,d*.62,base_h),(0,d*.16,base_h/2),'dark',.006)
            box('keyboard',(w*.88,d*.50,.008),(0,d*.16,base_h+.005),'metal',.002)
            screen_h=max(.08,h-base_h)
            box('screen_frame',(w,.025,screen_h),(0,-d*.14,base_h+screen_h/2),'dark',.006)
            box('screen',(w-.035,.008,screen_h-.035),(0,-d*.126,base_h+screen_h/2),'screen',.002)

        elif k=='wardrobe_drawers':
            box('body',(w,d,h),(0,0,h/2),'oak',.008)
            front_y=d/2+.008
            left_w=w/2-.012
            right_w=w/2-.012
            box('left_full_door',(left_w,.025,h-.025),(-w/4,front_y,h/2),'oak',.004)
            upper_h=h*.47
            box('right_upper_door',(right_w,.025,upper_h),(w/4,front_y,h-upper_h/2),'oak',.004)
            drawer_h=(h-upper_h)/3
            for n in range(3):
                z=(n+.5)*drawer_h
                box('drawer_'+str(n+1),(right_w,.025,drawer_h-.015),(w/4,front_y,z),'oak',.004)
                box('drawer_handle_'+str(n+1),(.16,.025,.018),(w/4,front_y+.025,z),'metal',.003)
            box('left_handle',(.025,.025,.18),(-.035,front_y+.025,h*.53),'metal',.003)
            box('right_handle',(.025,.025,.18),(.035,front_y+.025,h*.74),'metal',.003)

        elif k=='micke_desk':
            top=.035;side=.035;drawer=.10
            box('top',(w,d,top),(0,0,h-top/2),'oak',.006)
            for x in [-w/2+side/2,w/2-side/2]:box('side',(side,d,h-top),(x,0,(h-top)/2),'oak',.004)
            box('rear_rail',(w-side*2,.035,.12),(0,-d/2+.018,h-.15),'oak',.004)
            box('drawer',(w-side*2,.36,drawer),(0,d*.12,h-top-drawer/2),'oak',.005)
            box('drawer_front',(w-side*2,.02,drawer-.012),(0,d*.30,h-top-drawer/2),'oak',.003)
            box('drawer_handle',(.18,.025,.018),(0,d*.315,h-top-drawer/2),'metal',.003)

        elif k=='kobra4':
            base_h=h*.12
            box('base',(w,d*.72,base_h),(0,d*.08,base_h/2),'ceramic',.018)
            box('bed',(w*.88,d*.78,.025),(0,d*.03,base_h+.045),'dark',.005)
            gantry_y=-d*.20
            column_w=max(.025,w*.075)
            column_h=h*.72
            for x in [-w*.42,w*.42]:box('gantry_column',(column_w,.045,column_h),(x,gantry_y,base_h+column_h/2),'metal',.004)
            top_z=base_h+column_h
            box('gantry_top',(w*.91,.045,.045),(0,gantry_y,top_z),'metal',.004)
            rail_z=base_h+column_h*.55
            box('x_rail',(w*.84,.055,.055),(0,gantry_y+.015,rail_z),'metal',.004)
            box('toolhead',(w*.16,.10,h*.16),(0,gantry_y+.055,rail_z-.035),'dark',.012)
            cylinder('toolhead_fan',min(w,h)*.045,.012,(0,gantry_y+.11,rail_z-.02),'metal',(math.pi/2,0,0))
            box('display',(w*.22,.035,h*.14),(w*.35,d*.30,base_h+.07),'dark',.006)
            box('display_glass',(w*.19,.008,h*.11),(w*.35,d*.322,base_h+.07),'screen',.003)

        elif k=='skadis_filament':
            box('pegboard',(w,d,h),(0,0,h/2),'linen',.006)
            hole=.012
            for row in range(7):
                for col in range(7):
                    x=-w*.39+col*(w*.78/6);z=h*.10+row*(h*.80/6)
                    box('peg_hole',(hole,.006,hole),(x,d/2+.004,z),'dark',.001)
            roll_radius=min(w,h)*.115
            roll_depth=.065
            positions=[(-w*.24,h*.72),(0,h*.72),(w*.24,h*.72),(-w*.24,h*.35),(0,h*.35),(w*.24,h*.35)]
            colours=['blue','linen','dark','fabric_light','blue','oak']
            for n,((x,z),colour) in enumerate(zip(positions,colours),1):
                cylinder('filament_'+str(n),roll_radius,roll_depth,(x,d/2+roll_depth/2+.015,z),colour,(math.pi/2,0,0))
                cylinder('spool_hub_'+str(n),roll_radius*.34,roll_depth+.008,(x,d/2+roll_depth/2+.019,z),'dark',(math.pi/2,0,0))

        elif k=='storage_rack':
            post=.035
            for x in [-w/2+post/2,w/2-post/2]:
                for y in [-d/2+post/2,d/2-post/2]:
                    box('post',(post,post,h),(x,y,h/2),'metal',.003)
            shelf_levels=[.04,h*.27,h*.52,h*.77,h-.04]
            for n,z in enumerate(shelf_levels,1):
                box('shelf_'+str(n),(w,d,.035),(0,0,z),'metal',.003)
            for level,z in enumerate([h*.29,h*.54,h*.79],1):
                count=max(1,int(w/.42))
                box_w=min(.34,max(.18,(w-.08)/count))
                for n in range(count):
                    x=-w/2+(n+.5)*w/count
                    box('box_'+str(level)+'_'+str(n+1),(box_w,d*.62,.28),(x,0,z+.14),'basket',.012)

        elif k=='laundry_basket':
            box('body',(w,d,h),(0,0,h/2),'basket',.025)
            box('opening',(w*.78,d*.78,.015),(0,0,h-.008),'dark',.006)
            for z in [h*.25,h*.50,h*.75]:
                box('woven_front',(w*.84,.008,.012),(0,d/2+.005,z),'oak',.002)
                box('woven_back',(w*.84,.008,.012),(0,-d/2-.005,z),'oak',.002)

        elif k=='wall_radiator':
            # Slim wall-mounted panel radiator with vertical front ribs.
            box('radiator_body',(w,d,h),(0,0,h/2),'ceramic',.012)
            ribs=max(5,int(w/.075))
            rib_w=max(.012,w/(ribs*4))
            for n in range(ribs):
                x=-w/2+(n+.5)*w/ribs
                box('rib_'+str(n+1),(rib_w,.012,h-.06),(x,d/2+.008,h/2),'metal',.002)
            box('top_grille',(w-.04,d*.72,.018),(0,0,h-.012),'metal',.003)
            cylinder('valve',.025,.08,(w/2-.05,d/2+.035,.10),'metal',(math.pi/2,0,0))

    return objects
