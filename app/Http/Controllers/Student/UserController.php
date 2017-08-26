<?php

namespace App\Http\Controllers\Student;

use App\Model\Student;
use App\Model\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

//use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**注册
     * @param Request $request
     * @return mixed
     */
    public function student_join(Request $request)
    {
        $type = 'S1001';
        $post = $request->all();
        tourist_pretreat($type, $post);
        $add_user['name'] = $post['name'];
        $add_user['password'] = $post['password'];
        $add_user['sex'] = $post['sex'];
        $add_user['email'] = $post['email'];
        $add_user['school_num'] = $post['school_num'];
        $add_user['grade'] = '';
        $add_user['class_name'] = '';
        $add_user['school_id'] = $post['school_id'];
        $add_user['college_id'] = $post['college_id'];
        //检查数据库中是否存在该用户名,email,学号
        $users = DB::table('students')
            ->where('name', $post['name'])
            ->orWhere('email', $post['name'])
            ->orWhere('school_num', $post['school_num'])
            ->get()->toArray();
//        var_dump($users);die;
        if ($users) {
            return response_treatment(3, $type);
        }
        if (Student::create($add_user)) {
            return response_treatment(0, $type);
        } else {
            return response_treatment(1, $type);
        }


    }

    /**处理问卷
     * @param Request $request
     * @return mixed
     */
    public function QE(Request $request)
    {
        $type = 'S1007';
        $post = $request->all();
        login_pretreat($type,$post);
        $student_id = session('id');
        $QE_info = DB::table('attribute')->where('id',$student_id)->get();
        if(!$QE_info) {
            $style = [];
            for ($i = 1; $i <= 4; $i++) {
                $style[$i] = 0;
            }
            $style[1] = $post['answer1'] + $post['answer2'] + $post['answer3'] + $post['answer4'] + $post['answer5'];
            $style[2] = $post['answer6'] + $post['answer7'] + $post['answer8'] + $post['answer9'] + $post['answer10'];
            $style[3] = $post['answer11'] + $post['answer12'] + $post['answer13'] + $post['answer14'] + $post['answer15'];
            $style[4] = $post['answer16'] + $post['answer17'] + $post['answer18'] + $post['answer19'] + $post['answer20'];
            $styleword = [];
            if ($style[1] > 0) {
                $styleword[1] = '活跃型';
            } else {
                $styleword[1] = '沉思型';
            }

            if ($style[2] > 0) {
                $styleword[2] = '感悟型';
            } else {
                $styleword[2] = '直觉型';
            }

            if ($style[3] > 0) {
                $styleword[3] = '视觉型';
            } else {
                $styleword[3] = '言语型';
            }

            if ($style[4] > 0) {
                $styleword[4] = '序列型';
            } else {
                $styleword[4] = '综合型';
            }

            $msg = [];
            $msg['student_id'] = $student_id;
            $msg['style_count'] = $style;
            $msg['style_word'] = $styleword;
            $num = DB::table('attribute')
                ->where('student_id', $student_id)
                ->get();
            $num = count($num);
            if ($num == 0) {
                DB::table('attribute')->insert([
                    'student_id' => $student_id,
                    'style_1' => $style[1],
                    'style_2' => $style[2],
                    'style_3' => $style[3],
                    'style_4' => $style[4],
                ]);
            } else {
                DB::table('attribute')
                    ->where('student_id', $student_id)
                    ->update([
                        'student_id' => $student_id,
                        'style_1' => $style[1],
                        'style_2' => $style[2],
                        'style_3' => $style[3],
                        'style_4' => $style[4],
                    ]);
            }
        }
        return response_treatment(0,$type,$msg);

    }

    /**登录
     * @param Request $request
     * @return mixed
     */
    public function student_login(Request $request)
    {
        $type = 'S1002';
        $post = $request->all();
        tourist_pretreat($type, $post);
        $email = $post['email'];
        $password = $post['password'];
        $row = Student::where('email', $email)->first();
        //用户是否存在
        if ($row) {
            //密码是否正确
            if ($row->password == $password) {
                //生成token，存入数据库并且反回
                $token = $this->getToken($email, $password);
                $update['token'] = $token;
                Student::where('email', $email)->update($update);
                //个人id存进session
                Session::flush();
                $request->session()->put('id', $row->id);
                $msg['name'] = $row->name;
                $msg['email'] = $row->email;
                $res = response_treatment(0, $type, $msg);
                $res['token'] = $token;
                //记录登陆情况
                $time =  date('Y-m-d H:i:s');
                DB::table('usage')
                    ->insert([
                        'user_id'=>$row->id,
                        'user_name'=>$row->name,
                        'user_type'=>'学生端',
                        'email'=>$row->email,
                        'record'=>$time
                    ]);
                return $res;
            } else {
                return response_treatment(1, $type);
            }
        } else {
            return response_treatment(5, $type);
        }
    }

    /**生成token
     * @param $email
     * @param $password
     * @return string
     */
    private function getToken($email, $password)
    {
        $time = time();
        return md5($email . $password . $time);
    }
}
