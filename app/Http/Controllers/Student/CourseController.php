<?php

namespace App\Http\Controllers\Student;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{

    /**加入课程
     * @param Request $request
     * @return mixed
     */
    public function join_course(Request $request)
    {
        $type = 'S2001';
        $post = $request->all();
        login_pretreat($type, $post);
        $student_id=session('id');
        $QE_info = DB::table('attribute')->where('id',$student_id)->get();
        if($QE_info==0){
            return response_treatment(6,$type);
        }
        $add_take['course_id'] = $post['course_id'];
        $add_take['class_id'] = $post['class_id'];
        $add_take['pre_course_score'] = $post['pre_course_score'];
        $add_take['aim_score'] = $post['aim_score'];
        $add_take['aim_text'] = $post['aim_text'];
        $add_take['student_id'] = session('id');
        $flag = DB::table('student_course')->insert($add_take);
        if ($flag) {
            return response_treatment(0, $type);
        } else {
            return response_treatment(1, $type);
        }
    }

    /**获取该学生参加了的课程
     * @param Request $request
     */
    public function get_joined_course_list(Request $request)
    {
        $type = 'S2002';
        $post = $request->all();
        login_pretreat($type, $post);
        $student_id = session('id');
        //student_course表中找当前时间
        $courses = DB::table('student_course')->where('student_id', $student_id)->get()->toArray();
        return response_treatment(0, $type, $courses);
    }

    /**学生：根据课程id获取课程信息
     * @param Request $request
     */
    public function get_course_info_by_id(Request $request)
    {
        $type = 'S2003';
        $post = $request->all();
        login_pretreat($type, $post);
        //course表中找
        $course_info = DB::table('courses')->where('id', $post['course_id'])->first();
        //整合老师姓名
        $course_info->teacher_name = DB::table('teachers')->where('id', $course_info->teacher_id)->value('name');
        //整合上课班级
        $course_info->classes = DB::table('classes')->where('course_id', $post['course_id'])->get()->toArray();
        if ($course_info) {
            return response_treatment(0, $type, $course_info);
        } else {
            return response_treatment(1, $type);
        }
    }

    /**根据课程获取班别
     * @param Request $request
     */
    public function get_class_by_course(Request $request)
    {
        $type = 'S2004';
        $post = $request->all();
        login_pretreat($type, $post);
        //classes表
        $classes = DB::table('classes')->where('course_id', $post['course_id'])->get()->toArray();
        if ($classes) {
            return response_treatment(0, $type, $classes);
        } else {
            return response_treatment(1, $type);
        }
    }

    /**
     * 获取所有可添加课程
     */
    public function get_all_joinable_course(Request $request)
    {
        $type = 'S2005';
        $post = $request->all();
        login_pretreat($type, $post);
        //courses
        $courses = DB::table('courses')->where('state', '<>', 2)->get()->toArray();
        //整合教师姓名
        foreach ($courses as $course) {
            $course->teacher_name = DB::table('teachers')->where('id', $course->teacher_id)->value('name');
        }
        return response_treatment(0, $type, $courses);
    }
    private function get_object_value_as_array($objects,$value_name){
        $value_list=[];
        foreach ($objects as $object){
            $value_list[]=$object->$value_name;
        }
        return $value_list;}
    public function get_communicatemsg(Request $request)
    {
        $type = 'S2030';
        $post = $request->all();
        login_pretreat($type,$post);
        $student_id =session('id');
        $student_name = DB::table('students')->where('id',$student_id)->value('name');
        $student_info = DB::table('student_course')->where('student_id',$student_id)->get()->toArray();
        $class_ids = $this->get_object_value_as_array($student_info,'class_id');
        $get_communicatemsg['student_id']=$student_id;
        $get_communicatemsg['student_name']=$student_name;
        foreach($class_ids as $class_id)
        {
            $class_name = DB::table('classes')->where('id',$class_id)->value('class_name');
            $course_id = DB::table('classes')->where('id',$class_id)->value('course_id');
            $course_name = DB::table('courses')->where('id',$course_id)->value('name');
            $teacher_id = DB::table('courses')->where('id',$course_id)->value('teacher_id');
            $teacher_name = DB::table('teachers')->where('id',$teacher_id)->value('name');
            $group_type = DB::table('student_course')->where('class_id',$class_id)->value('grouptype');
            if($group_type==1)
            {
                $group_id = DB::table('student_course')->where('student_id',$student_id)->where('class_id',$class_id)->value('auto_group_id');
                $group_memberinfos = DB::table('student_course')->where('auto_group_id',$group_id)->where('class_id',$class_id)->get()->toArray();
                $group_memberids = $this->get_object_value_as_array($group_memberinfos,'student_id');
                $group_membernames = DB::table('students')->whereIn('id',$group_memberids)->get()->toArray();
                $group_membernames = $this->get_object_value_as_array($group_membernames,'name');
            }
            if($group_type==2)
            {
                $group_id = DB::table('student_course')->where('student_id',$student_id)->where('class_id',$class_id)->value('system_group_id');
                $group_memberinfos = DB::table('student_course')->where('system_group_id',$group_id)->where('class_id',$class_id)->get()->toArray();
                $group_memberids = $this->get_object_value_as_array($group_memberinfos,'student_id');
                $group_membernames = DB::table('students')->whereIn('id',$group_memberids)->get()->toArray();
                $group_membernames = $this->get_object_value_as_array($group_membernames,'name');
            }
            if($group_type==3)
            {
                $group_id = DB::table('student_course')->where('student_id',$student_id)->where('class_id',$class_id)->value('offline_group_id');
                $group_memberinfos = DB::table('student_course')->where('offline_group_id',$group_id)->where('class_id',$class_id)->get()->toArray();
                $group_memberids = $this->get_object_value_as_array($group_memberinfos,'student_id');
                $group_membernames = DB::table('students')->whereIn('id',$group_memberids)->get()->toArray();
                $group_membernames = $this->get_object_value_as_array($group_membernames,'name');
            }

            //groups
            $groups['group_id']=$group_id;
            $groups['groupmemberids']=$group_memberids;
            $groups['groupmembernames']=$group_membernames;
            $eachclass_info['class_id']=$class_id;
            $eachclass_info['class_name']=$class_name;
            $eachclass_info['teacher_name']=$teacher_name;
            $eachclass_info['groups']=$groups;
            $class_info[] = $eachclass_info;
        }
        $msg['student_id']=$student_id;
        $msg['student_name']=$student_name;
        $msg['class_info']=$class_info;
        return response_treatment(0,$type,$msg);
    }
    public function post_communicate(Request $request)
    {
        $type = 'S2008';
        $post = $request->all();
        login_pretreat($type,$post);
        $student_id =session('id');
        $class_id = $post['class_id'];
        $communicate_type = $post['communicate_type'];
        $communicate_content = $post['communicate_content'];
        $time =  date('Y-m-d H:i:s');
        if ($communicate_type == 1) {
            DB::table('communicate')
                ->insert(['communicate_type' => 11, 'student_id' => $student_id, 'class_id' => $class_id, 'communicate_content' => $communicate_content,'post_at'=>$time]);
        }
        if($communicate_type==2){
            DB::table('communicate')
                ->insert(['communicate_type' => 21, 'student_id' => $student_id, 'class_id' => $class_id, 'communicate_content' => $communicate_content,'post_at'=>$time]);
        }
        $msg = '发表成功';
        return response_treatment(0,$type,$msg);
    }
    public function get_gs_communicate(Request $request)
    {
        $type = 'S2031';
        $post = $request->all();
        login_pretreat($type,$post);
        $student_id =session('id');
        //得到所设及的班级id;
        $class_ids = DB::table('student_course')->where('student_id',$student_id)->get()->toArray();
        $class_ids = $this->get_object_value_as_array($class_ids,'class_id');
        foreach($class_ids as $class_id)
        {
            //课程信息
            $class_name=DB::table('classes')->where('id',$class_id)->value('class_name');
            $course_id = DB::table('classes')->where('id',$class_id)->value('course_id');
            $course_name = DB::table('courses')->where('id',$course_id)->value('name');
            $teacher_id = DB::table('courses')->where('id',$course_id)->value('teacher_id');
            $teacher_name = DB::table('teachers')->where('id',$teacher_id)->value('name');
            $group_type = DB::table('student_course')->where('class_id',$class_id)->value('grouptype');
            if($group_type==1)
            {
                $group_id = DB::table('student_course')->where('class_id', $class_id)->where('student_id', $student_id)->value('auto_group_id');
                $student_ids = DB::table('student_course')->where('class_id',$class_id)->where('auto_group_id',$group_id)->get()->toArray();
                $student_ids = $this->get_object_value_as_array($student_ids,'student_id');
                $student_names = DB::table('students')->whereIn('id',$student_ids)->get()->toArray();
                $student_names = $this->get_object_value_as_array($student_names,'name');
            }
            if($group_type==2)
            {
                $group_id = DB::table('student_course')->where('class_id', $class_id)->where('student_id', $student_id)->value('system_group_id');
                $student_ids = DB::table('student_course')->where('class_id',$class_id)->where('system_group_id',$group_id)->get()->toArray();
                $student_ids = $this->get_object_value_as_array($student_ids,'student_id');
                $student_names = DB::table('students')->whereIn('id',$student_ids)->get()->toArray();
                $student_names = $this->get_object_value_as_array($student_names,'name');
            }
            if($group_type==3)
            {
                $group_id = DB::table('student_course')->where('class_id', $class_id)->where('student_id', $student_id)->value('offline_group_id');
                $student_ids = DB::table('student_course')->where('class_id',$class_id)->where('offline_group_id',$group_id)->get()->toArray();
                $student_ids = $this->get_object_value_as_array($student_ids,'student_id');
                $student_names = DB::table('students')->whereIn('id',$student_ids)->get()->toArray();
                $student_names = $this->get_object_value_as_array($student_names,'name');
            }
            $communicate_content = DB::table('communicate')->whereIn('student_id',$student_ids)->where('class_id',$class_id)->whereIn('communicate_type',[21,22])->get()->toArray();
            $each_gs_communicatemsg['class_id']=$class_id;
            $each_gs_communicatemsg['class_name']=$class_name;
            $each_gs_communicatemsg['course_name']=$course_name;
            $each_gs_communicatemsg['teacher_name']=$teacher_name;
            $each_gs_communicatemsg['group_memberids']=$student_ids;
            $each_gs_communicatemsg['group_membernames']=$student_names;
            $each_gs_communicatemsg['communicate_content']=$communicate_content;
            $gs_communicatemsg[]=$each_gs_communicatemsg;
        }
        return response_treatment(0,$type,$gs_communicatemsg);

    }
    public function get_st_communicate(Request $request)
    {
        $type = 'S2032';
        $post = $request->all();
        login_pretreat($type,$post);
        $student_id =session('id');
        $student_name = DB::table('students')->where('id',$student_id)->value('name');
        $student_info = DB::table('student_course')->where('student_id',$student_id)->get()->toArray();
        $class_ids = $this->get_object_value_as_array($student_info,'class_id');
        foreach($class_ids as $class_id)
        {
            $class_name = DB::table('classes')->where('id',$class_id)->value('class_name');
            $course_id = DB::table('classes')->where('id',$class_id)->value('course_id');
            $course_name = DB::table('courses')->where('id',$course_id)->value('name');
            $teacher_id = DB::table('courses')->where('id',$course_id)->value('teacher_id');
            $teacher_name = DB::table('teachers')->where('id',$teacher_id)->value('name');
            $communicate_content=DB::table('communicate')->where('student_id',$student_id)->where('class_id',$class_id)->whereIn('communicate_type',[11,12])->get()->toArray();
            $each_st_communicate['class_id']=$class_id;
            $each_st_communicate['class_name']=$class_name;
            $each_st_communicate['course_name']=$course_name;
            $each_st_communicate['teacher_name']=$teacher_name;
            $each_st_communicate['student_name']=$student_name;
            $each_st_communicate['communicate_content']=$communicate_content;
            $st_communicate[]=$each_st_communicate;
        }
        return response_treatment(0,$type,$st_communicate);

    }
    public function get_cs_communicate(Request $request)
    {
        $type = 'S2033';
        $post = $request->all();
        login_pretreat($type,$post);
        $student_id =session('id');
        $student_name = DB::table('students')->where('id',$student_id)->value('name');
        $student_info = DB::table('student_course')->where('student_id',$student_id)->get()->toArray();
        $class_ids = $this->get_object_value_as_array($student_info,'class_id');
        foreach($class_ids as $class_id)
        {
            $class_name = DB::table('classes')->where('id',$class_id)->value('class_name');
            $course_id = DB::table('classes')->where('id',$class_id)->value('course_id');
            $course_name = DB::table('courses')->where('id',$course_id)->value('name');
            $teacher_id = DB::table('courses')->where('id',$course_id)->value('teacher_id');
            $teacher_name = DB::table('teachers')->where('id',$teacher_id)->value('name');
            $communicate_content=DB::table('communicate')->where('student_id',$student_id)->where('class_id',$class_id)->where('communicate_type',3)->get()->toArray();
            $each_cs_communicate['class_id']=$class_id;
            $each_cs_communicate['class_name']=$class_name;
            $each_cs_communicate['course_name']=$course_name;
            $each_cs_communicate['teacher_name']=$teacher_name;
            $each_cs_communicate['student_name']=$student_name;
            $each_cs_communicate['communicate_content']=$communicate_content;
            $cs_communicate[]=$each_cs_communicate;
        }
        return response_treatment(0,$type,$cs_communicate);


    }

}
