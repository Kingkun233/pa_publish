<?php

namespace App\Http\Controllers\Teacher;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    /**添加课程
     * @param Request $request
     * @return mixed
     */
    public function add_course(Request $request)
    {
        $type = 'T2001';
        $post = $request->all();
        login_pretreat($type, $post);
        $add_course['name'] = $post['name'];
        $add_course['grade'] = $post['grade'];
        $add_course['description'] = $post['description'];
        $add_course['start_day'] = $post['start_day'];
        $add_course['end_day'] = $post['end_day'];
        $add_course['total_hours'] = $post['total_hours'];
        $add_course['pre_course_name'] = $post['pre_course_name'];
        $add_course['aim'] = $post['aim'];
        $add_course['progress'] = $post['progress'];
        $add_course['teacher_id'] = session('id');
        $add_course['school_id'] = DB::table('teachers')->where('id', $add_course['teacher_id'])->first()->school_id;
        DB::beginTransaction();
        $flag = DB::table('courses')->insertGetId($add_course);
        if ($flag) {
            //插入班级表
            foreach ($post['classes'] as $class_name) {
                DB::table('classes')->insert(['class_name' => $class_name, 'course_id' => $flag]);
            }
            DB::commit();
            return response_treatment(0, $type);
        } else {
            DB::rollback();
            return response_treatment(1, $type);
        }
    }

    /**
     * 老师：获取我开设的所有课程
     */
    public function get_my_course(Request $request)
    {
        $type = 'T2002';
        $post = $request->all();
        login_pretreat($type, $post);
        $teacher_id = session('id');
        //course
        $courses = DB::table('courses')->where('teacher_id', $teacher_id)->get()->toArray();
        if ($courses) {
            return response_treatment(0, $type, $courses);
        } else {
            return response_treatment(1, $type);
        }
    }


    /**获取该课程的学生
     * @param Request $request
     */
    public function get_student_by_course(Request $request)
    {
        $type = 'T2003';
        $post = $request->all();
        login_pretreat($type, $post);
        $course_id = $post['course_id'];
        $students = DB::table('student_course')->where('course_id', $course_id)->get()->toArray();
        foreach ($students as $student) {
            //整合学生信息
            $student_info = DB::table('students')->where('id', $student->student_id)->first();
            $student->name = $student_info->name;
            $student->school_num = $student_info->school_num;
        }

        $student_group_by_class = [];
        $class_ids = array_unique(get_object_value_as_array($students, 'class_id'));
//        var_dump($class_ids);die;
        foreach ($class_ids as $class_id) {
            $student_group_by_class[] = ['class_id' => $class_id];
        }
        foreach ($students as $student) {
            $key = 0;
            foreach ($student_group_by_class as $k => $v) {
                if ($v['class_id'] == $student->class_id) {
                    $key = $k;
                }
            }
            $student_group_by_class[$key]['class_name'] = DB::table('classes')->where('id', $student->class_id)->value('class_name');
            $student_group_by_class[$key]['students'][] = $student;
        }
        return response_treatment(0, $type, $student_group_by_class);
    }

    /**将对象数组按照每个属性分类返回二维对象数组
     * @param $array_one_degree
     * @param $key
     */
    private function group($objs, $attr)
    {
        $return_array = [];
        foreach ($objs as $obj) {
            $return_array[$obj->$attr][] = $obj;
        }
    }
    private function get_object_value_as_array($objects,$value_name){
        $value_list=[];
        foreach ($objects as $object){
            $value_list[]=$object->$value_name;
        }
        return $value_list;
    }
    /*分组模块*/
    public function grouping(Request $request)
    {
        $type='T2004';
        $post=$request->all();
        login_pretreat($type,$post);
        $groupingtype = $post['groupingtype'];
        $class_id = $post['class_id'];
        //得到班级名
        $class_name = DB::table('classes')
            ->where('id',$class_id)
            ->value('class_name');
        $course_id = DB::table('classes')
            ->where('id',$class_id)
            ->value('course_id');
        $course_name = DB::table('courses')
            ->where('id',$course_id)
            ->value('name');
        /*echo $class_name;*/
        //自动随机分组
        if ($groupingtype == 1)
        {
            system("C:/Users/95825/Desktop/pa-master/app/Common/randomgrouping.py $class_id");
            DB::table('student_course')
                ->where('class_id',$class_id)
                ->update(['grouptype'=>1]);
        }
        //自动系统分组
        if ($groupingtype == 2)
        {
            system("C:/Users/95825/Desktop/pa-master/app/Common/system_grouping.py $class_id");
            DB::table('student_course')
                ->where('class_id',$class_id)
                ->update(['grouptype'=>2]);
        }
        //线下分组
        if($groupingtype == 3)
        {
            if(!$request->hasFile('file')){
                exit('上传文件为空！');
            }
            $file = $_FILES;
            $excel_file_path = $file['file']['tmp_name'];
            $res = [];
            \Maatwebsite\Excel\Facades\Excel::load($excel_file_path, function($reader) use( &$res ) {
                $reader = $reader->getSheet(0);
                $res = $reader->toArray();
            });
            for($i=1;$i<=(count($res)-1);$i++)
            {
                $student_num = $res[$i][1];
                $student_id = DB::table('students')
                    ->where(['school_num'=>$student_num])
                    ->value('id');
                $group_id = $res[$i][2];
                DB::table('student_course')
                    ->where(['student_id'=>$student_id])
                    ->update(['offline_group_id'=>$group_id,'grouptype'=>3]);
            }

        }
        if ($groupingtype == 1)
        {
            //获取所有group_id
            $groups=DB::table('student_course')->where('class_id',$post['class_id'])->get()->toArray();
            $group_ids=$this->get_object_value_as_array($groups,'auto_group_id');
            $group_ids=array_unique($group_ids);
            $groupsinfo=[];
            foreach($group_ids as $group_id)
            {
                $student_courses=DB::table('student_course')->where(['auto_group_id'=>$group_id])->where(['class_id'=>$class_id])->get()->toArray();
                $student_ids = $this->get_object_value_as_array($student_courses,'student_id');
                $student_infos = DB::table('students')->whereIn('id',$student_ids)->select('name','school_num')->get()->toArray();
                $group = [];
                $group['group_id']=$group_id;
                $group['group_membermsg']=$student_infos;
                $groupsinfo[]=$group;
            }
        }
        if ($groupingtype == 2)
        {
            //获取所有group_id
            $groups=DB::table('student_course')->where('class_id',$post['class_id'])->get()->toArray();
            $group_ids=$this->get_object_value_as_array($groups,'system_group_id');
            $group_ids=array_unique($group_ids);
            $groupsinfo=[];
            foreach($group_ids as $group_id)
            {
                $student_courses=DB::table('student_course')->where(['system_group_id'=>$group_id])->where(['class_id'=>$class_id])->get()->toArray();
                $student_ids = $this->get_object_value_as_array($student_courses,'student_id');
                $student_infos = DB::table('students')->whereIn('id',$student_ids)->select('name','school_num')->get()->toArray();
                $group = [];
                $group['group_id']=$group_id;
                $group['group_membermsg']=$student_infos;
                $groupsinfo[]=$group;
            }
        }
        if ($groupingtype == 3)
        {
            //获取所有group_id
            $groups=DB::table('student_course')->where('class_id',$post['class_id'])->get()->toArray();
            $group_ids=$this->get_object_value_as_array($groups,'offline_group_id');
            $group_ids=array_unique($group_ids);
            $groupsinfo=[];
            foreach($group_ids as $group_id)
            {
                $student_courses=DB::table('student_course')->where(['offline_group_id'=>$group_id])->where(['class_id'=>$class_id])->get()->toArray();
                $student_ids = $this->get_object_value_as_array($student_courses,'student_id');
                $student_infos = DB::table('students')->whereIn('id',$student_ids)->select('name','school_num')->get()->toArray();
                $group = [];
                $group['group_id']=$group_id;
                $group['group_membermsg']=$student_infos;
                $groupsinfo[]=$group;
            }
        }
        $want_info['class_name'] = $class_name;
        $want_info['course_name']=$course_name;
        $want_info['groupinfo'] = $groupsinfo;
        return response_treatment(0,$type,$want_info);
        /*return $groups;*/
    }
    public function adjust_grouping(Request $request)
    {
        $type = 'T2007';
        $post = $request->all();
        login_pretreat($type,$post);
        $class_id = $post['class_id'];
        $pre_grouptype = $post['pre_grouptype'];
        $adjust_student_name=$post['adjust_student_name'];
        $adjust_student_num = $post['adjust_student_num'];
        $adjust_student_togroup_id = $post['adjust_student_togroup_id'];
        $adjust_student_id = DB::table('students')
            ->where(['school_num'=>$adjust_student_num])
            ->value('id');
        $num = DB::table('student_course')
            ->where(['student_id'=>$adjust_student_id,'class_id'=>$class_id])
            ->get();
        $num = count($num);
        if($num==0)
        {
            $msg = '该名学生尚未注册课程，请通知注册在执行操作';
            return response_treatment(0,$type,$msg);
        }else {
            if ($pre_grouptype == 1) {
                DB::table('student_course')
                    ->where(['student_id' => $adjust_student_id, 'class_id' => $class_id])
                    ->update(['auto_group_id' => $adjust_student_togroup_id]);
            }
            $msg = '操作成功';
            if ($pre_grouptype == 2) {
                DB::table('student_course')
                    ->where(['student_id' => $adjust_student_id, 'class_id' => $class_id])
                    ->update(['system_group_id' => $adjust_student_togroup_id]);
            }
            $msg = '操作成功';
            if ($pre_grouptype == 3) {
                DB::table('student_course')
                    ->where(['student_id' => $adjust_student_id, 'class_id' => $class_id])
                    ->update(['offline_group_id' => $adjust_student_togroup_id]);
            }
            $msg = '操作成功';
        }
        return response_treatment(0,$type,$msg);

    }
    public function get_groupingmsg(Request $request)
    {
        $type='T2005 ';
        $post=$request->all();
        /*login_pretreat($type,$post);*/
        $class_id = $post['class_id'];
        $class_name = DB::table('classes')
            ->where('id',$class_id)
            ->value('class_name');
        $course_id = DB::table('classes')
            ->where('id',$class_id)
            ->value('course_id');
        $course_name = DB::table('courses')
            ->where('id',$course_id)
            ->value('name');

        $group_type=DB::table('student_course')
            ->where(['class_id'=>$class_id])
            ->value('grouptype');

        if ($group_type == 1)
        {
            //获取所有group_id
            $groups=DB::table('student_course')->where('class_id',$post['class_id'])->get()->toArray();
            $group_ids=$this->get_object_value_as_array($groups,'auto_group_id');
            $group_ids=array_unique($group_ids);
            $groupsinfo=[];
            foreach($group_ids as $group_id)
            {
                $student_courses=DB::table('student_course')->where(['auto_group_id'=>$group_id])->where(['class_id'=>$class_id])->get()->toArray();
                $student_ids = $this->get_object_value_as_array($student_courses,'student_id');
                $student_infos = DB::table('students')->whereIn('id',$student_ids)->select('name','school_num')->get()->toArray();
                $group = [];
                $group['group_id']=$group_id;
                $group['group_membermsg']=$student_infos;
                $groupsinfo[]=$group;
            }
        }
        if ($group_type == 2)
        {
            //获取所有group_id
            $groups=DB::table('student_course')->where('class_id',$post['class_id'])->get()->toArray();
            $group_ids=$this->get_object_value_as_array($groups,'system_group_id');
            $group_ids=array_unique($group_ids);
            $groupsinfo=[];
            foreach($group_ids as $group_id)
            {
                $student_courses=DB::table('student_course')->where(['system_group_id'=>$group_id])->where(['class_id'=>$class_id])->get()->toArray();
                $student_ids = $this->get_object_value_as_array($student_courses,'student_id');
                $student_infos = DB::table('students')->whereIn('id',$student_ids)->select('name','school_num')->get()->toArray();
                $group = [];
                $group['group_id']=$group_id;
                $group['group_membermsg']=$student_infos;
                $groupsinfo[]=$group;
            }
        }
        if ($group_type == 3)
        {
            //获取所有group_id
            $groups=DB::table('student_course')->where('class_id',$post['class_id'])->get()->toArray();
            $group_ids=$this->get_object_value_as_array($groups,'offline_group_id');
            $group_ids=array_unique($group_ids);
            $groupsinfo=[];
            foreach($group_ids as $group_id)
            {
                $student_courses=DB::table('student_course')->where(['offline_group_id'=>$group_id])->where(['class_id'=>$class_id])->get()->toArray();
                $student_ids = $this->get_object_value_as_array($student_courses,'student_id');
                $student_infos = DB::table('students')->whereIn('id',$student_ids)->select('name','school_num')->get()->toArray();
                $group = [];
                $group['group_id']=$group_id;
                $group['group_membermsg']=$student_infos;
                $groupsinfo[]=$group;
            }
        }
        $want_info['class_name'] = $class_name;
        $want_info['course_name']=$course_name;
        $want_info['groupinfo'] = $groupsinfo;
        return response_treatment(0,$type,$want_info);

    }
    //反馈模块
    /*发表信息*/
    public function post_communicate(Request $request)
    {
        $type = 'T2009';
        $post = $request->all();
        login_pretreat($type,$post);
        $class_id = $post['class_id'];
        $group_id = $post['group_id'];
        $student_id=$post['student_id'];
        $communicate_type=$post['communicate_type'];
        $communicate_content=$post['communicate_content'];
        //对个人反馈
        $msg=[];
        if($communicate_type==1)
        {
            $time =  date('Y-m-d H:i:s');
            DB::table('communicate')
                ->insert(['communicate_type'=>12,'student_id'=>$student_id,'class_id'=>$class_id,'communicate_content'=>$communicate_content,'post_at'=>$time]);
            $msg['communicate_type']='个人私信';
            $msg['content'] = '发表成功';
        }
        //对小组
        if($communicate_type==2)
        {
            $get_group_type = DB::table('student_course')
                ->where(['class_id'=>$class_id])
                ->value('grouptype');
            if($get_group_type==1)
            {
                $get_one_student_id = DB::table('student_course')
                    ->select('student_id')
                    ->where(['auto_group_id'=>$group_id,'class_id'=>$class_id])
                    ->get()->toArray();
                $get_one_student_id = $this->get_object_value_as_array($get_one_student_id,'student_id');
                $get_one_student_id =$get_one_student_id[0];

            }
            if($get_group_type==2)
            {
                $get_one_student_id = DB::table('student_course')
                    ->select('student_id')
                    ->where(['system_group_id'=>$group_id,'class_id'=>$class_id])
                    ->get()->toArray();
                $get_one_student_id = $this->get_object_value_as_array($get_one_student_id,'student_id');
                $get_one_student_id =$get_one_student_id[0];
            }
            if($get_group_type==3)
            {
                $get_one_student_id = DB::table('student_course')
                    ->select('student_id')
                    ->where(['offline_group_id'=>$group_id,'class_id'=>$class_id])
                    ->get()->toArray();
                $get_one_student_id = $this->get_object_value_as_array($get_one_student_id,'student_id');
                $get_one_student_id =$get_one_student_id[0];
            }
            $time =  date('Y-m-d H:i:s');
            DB::table('communicate')
                ->insert(['communicate_type'=>22,'student_id'=>$get_one_student_id,'class_id'=>$class_id,'communicate_content'=>$communicate_content,'post_at'=>$time]);
            $msg['communicate_type']='组内信息';
            $msg['content'] = '发表成功';
        }
        //公告
        if($communicate_type==3)
        {
            $time =  date('Y-m-d H:i:s');
            $get_one_student_id = DB::table('student_course')
                ->select('student_id')
                ->where(['class_id'=>$class_id])
                ->get()->toArray();
            $get_one_student_id = $this->get_object_value_as_array($get_one_student_id,'student_id');
            $get_one_student_id =$get_one_student_id[0];
            DB::table('communicate')
                ->insert(['communicate_type'=>3,'student_id'=>$get_one_student_id,'class_id'=>$class_id,'communicate_content'=>$communicate_content,'post_at'=>$time]);
            $msg['communicate_type']='公告';
            $msg['content'] = '发表成功';
        }

        return response_treatment(0,$type,$msg);


    }
    public function get_classInfo(Request $request)
    {
        $type = 'T2015';
        $post = $request->all();
        login_pretreat($type,$post);
        $teacher_id = session('id');
        //得到这个教师的所有班级
        $course_ids = DB::table('courses')->where('teacher_id',$teacher_id)->get()->toArray();
        $course_ids = $this->get_object_value_as_array($course_ids,'id');
        $class_ids = DB::table('classes')->whereIn('course_id',$course_ids)->get()->toArray();
        $class_ids = $this->get_object_value_as_array($class_ids,'id');
        //得到每个班级的信息
        foreach($class_ids as $class_id)
        {
            //班级信息
            $class_name = DB::table('classes')->where('id',$class_id)->value('class_name');
            $course_id = DB::table('classes')->where('id',$class_id)->value('course_id');
            $course_name = DB::table('courses')->where('id',$course_id)->value('name');
            $each_classmsg['class_id']=$class_id;
            $each_classmsg['class_name']=$class_name;
            $each_classmsg['course_name']=$course_name;
            //班级人员信息
            $class_memberids = DB::table('student_course')->where('class_id',$class_id)->get()->toArray();
            $class_memberids = $this->get_object_value_as_array($class_memberids,'student_id');
            $class_membermsg=DB::table('students')->select('name','school_num','id')->whereIn('id',$class_memberids)->get()->toArray();
            $each_classmsg['class_membermsg']=$class_membermsg;
            //班级分组信息
            $group_type=DB::table('student_course')
                ->where(['class_id'=>$class_id])
                ->value('grouptype');

            if ($group_type == 1)
            {
                //获取所有group_id
                $groups=DB::table('student_course')->where('class_id',$class_id)->get()->toArray();
                $group_ids=$this->get_object_value_as_array($groups,'auto_group_id');
                $group_ids=array_unique($group_ids);
                $groupsinfo=[];
                foreach($group_ids as $group_id)
                {
                    $student_courses=DB::table('student_course')->where(['auto_group_id'=>$group_id])->where(['class_id'=>$class_id])->get()->toArray();
                    $student_ids = $this->get_object_value_as_array($student_courses,'student_id');
                    $student_infos = DB::table('students')->whereIn('id',$student_ids)->select('name','school_num')->get()->toArray();
                    $group = [];
                    $group['group_id']=$group_id;
                    $group['group_membermsg']=$student_infos;
                    $groupsinfo[]=$group;
                }
            }
            if ($group_type == 2)
            {
                //获取所有group_id
                $groups=DB::table('student_course')->where('class_id',$class_id)->get()->toArray();
                $group_ids=$this->get_object_value_as_array($groups,'system_group_id');
                $group_ids=array_unique($group_ids);
                $groupsinfo=[];
                foreach($group_ids as $group_id)
                {
                    $student_courses=DB::table('student_course')->where(['system_group_id'=>$group_id])->where(['class_id'=>$class_id])->get()->toArray();
                    $student_ids = $this->get_object_value_as_array($student_courses,'student_id');
                    $student_infos = DB::table('students')->whereIn('id',$student_ids)->select('name','school_num')->get()->toArray();
                    $group = [];
                    $group['group_id']=$group_id;
                    $group['group_membermsg']=$student_infos;
                    $groupsinfo[]=$group;
                }
            }
            if ($group_type == 3)
            {
                //获取所有group_id
                $groups=DB::table('student_course')->where('class_id',$class_id)->get()->toArray();
                $group_ids=$this->get_object_value_as_array($groups,'offline_group_id');
                $group_ids=array_unique($group_ids);
                $groupsinfo=[];
                foreach($group_ids as $group_id)
                {
                    $student_courses=DB::table('student_course')->where(['offline_group_id'=>$group_id])->where(['class_id'=>$class_id])->get()->toArray();
                    $student_ids = $this->get_object_value_as_array($student_courses,'student_id');
                    $student_infos = DB::table('students')->whereIn('id',$student_ids)->select('name','school_num')->get()->toArray();
                    $group = [];
                    $group['group_id']=$group_id;
                    $group['group_membermsg']=$student_infos;
                    $groupsinfo[]=$group;
                }
            }
            $each_classmsg['class_groupmsg']=$groupsinfo;
            $all_classmsg[]=$each_classmsg;
        }
        return response_treatment(0,$type,$all_classmsg);
    }
    public function get_st_communicate(Request $request)
    {
        $type = 'T2008';
        $post = $request->all();
        //communicate_type:1/2/3
        login_pretreat($type,$post);
        $class_id = $post['class_id'];
        $student_id = $post['student_id'];
        //得到对话者信息
        $student_name = DB::table('students')->where('id',$student_id)->value('name');
        $course_id = DB::table('classes')->where('id',$class_id)->value('course_id');
        $teacher_id = DB::table('courses')->where('id',$course_id)->value('teacher_id');
        $teacher_name = DB::table('teachers')->where('id',$teacher_id)->value('name');
        $communicate_content = DB::table('communicate')->where('class_id', $class_id)->where('student_id', $student_id)->whereIn('communicate_type', [11,12])->get()->toArray();
        $communicate_info['student_name']=$student_name;
        $communicate_info['teacher_name']=$teacher_name;
        $communicate_info['communicate_content']=$communicate_content;
        return response_treatment(0,$type,$communicate_info);
    }
    public function get_gt_communicate(Request $request)
    {
        $type = 'T2020';
        $post = $request->all();
        login_pretreat($type,$post);
        $class_id = $post['class_id'];
        //获得班级信息
        $class_name = DB::table('classes')->where('id',$class_id)->value('class_name');
        $course_id = DB::table('classes')->where('id',$class_id)->value('course_id');
        $course_name = DB::table('courses')->where('id',$course_id)->value('name');
        //得到当前class的分组类型
        $group_type = DB::table('student_course')->where('class_id',$class_id)->value('grouptype');
        $group_id = $post['group_id'];
        if ($group_type==1)
        {
            $studentinfo=DB::table('student_course')->where('auto_group_id',$group_id)->where('class_id',$class_id)->get()->toArray();
            $student_ids=$this->get_object_value_as_array($studentinfo,'student_id');
        }
        if ($group_type==2)
        {
            $studentinfo=DB::table('student_course')->where('system_group_id',$group_id)->where('class_id',$class_id)->get()->toArray();
            $student_ids=$this->get_object_value_as_array($studentinfo,'student_id');
        }
        if ($group_type==3)
        {
            $studentinfo=DB::table('student_course')->where('offline_group_id',$group_id)->where('class_id',$class_id)->get()->toArray();
            $student_ids=$this->get_object_value_as_array($studentinfo,'student_id');
        }
        //得到通话者信息
        $student_infos = DB::table('students')->whereIn('id',$student_ids)->get()->toArray();
        $student_name = $this->get_object_value_as_array($student_infos,'name');
        $course_id = DB::table('classes')->where('id',$class_id)->value('course_id');
        $teacher_id = DB::table('courses')->where('id',$course_id)->value('teacher_id');
        $teacher_name = DB::table('teachers')->where('id',$teacher_id)->value('name');
        $communicate_content = DB::table('communicate')->where('class_id',$class_id)->whereIn('student_id',$student_ids)->whereIn('communicate_type',[21,22])->get()->toArray();
        $communicate_info['teacher_name']=$teacher_name;
        $communicate_info['class_name']=$class_name;
        $communicate_info['course_name']=$course_name;
        $communicate_info['group_id']=$group_id;
        $communicate_info['student_ids']=$student_ids;
        $communicate_info['student_name']=$student_name;
        $communicate_info['communicate_content']=$communicate_content;
        return response_treatment(0,$type,$communicate_info);
    }
    public function get_ct_communicate(Request $request)
    {
        $type = 'T2021';
        $post = $request->all();
        login_pretreat($type,$post);
        $class_id = $post['class_id'];
        //课程信息
        $class_name = DB::table('classes')->where('id',$class_id)->value('class_name');
        $course_id = DB::table('classes')->where('id',$class_id)->value('course_id');
        $course_name = DB::table('courses')->where('id',$course_id)->value('name');
        $teacher_id = DB::table('courses')->where('id',$course_id)->value('teacher_id');
        $teacher_name = DB::table('teachers')->where('id',$teacher_id)->value('name');
        $communicate_content=DB::table('communicate')->where('class_id',$class_id)->where('communicate_type',3)->get()->toArray();
        $communicate_info['class_id']=$class_id;
        $communicate_info['teacher_name']=$teacher_name;
        $communicate_info['class_name']=$class_name;
        $communicate_info['course_name']=$course_name;
        $communicate_info['communicate_content']=$communicate_content;
        return response_treatment(0,$type,$communicate_info);

    }
}
