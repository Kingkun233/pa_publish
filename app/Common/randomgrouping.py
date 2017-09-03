#还需要参数：课程号，班级号
#随机分组
import random
import pymysql
import sys
def randomgrouping(class_id):
    #读数据
    conn = pymysql.connect(user='root', password='111111', database='pa', charset='utf8')
    cursor = conn.cursor()
    query = ('select student_id from pa_student_course where class_id = %s')
    cursor.execute(query,(class_id))
    student_id = []
    for each in cursor:
        student_id.append(each[0])
    Group = []
    stopgrouping = 1

    while stopgrouping == 1:
        if len(student_id) >= 4:
            group = random.sample(student_id, 4)
            Group.append(group)
            for each in group:
                student_id.remove(each)
        if len(student_id) == 3:
            group = student_id[:]
            Group.append(group)
            for each in student_id:
                student_id.remove(each)
                if len(student_id) == 1:
                    student_id.pop()
        if len(student_id) == 1:
            for each in student_id:
                Group[-1].append(each)
                student_id.remove(each)
        if len(student_id) == 2:
                Group[-1].append(student_id[0])
                Group[-2].append(student_id[1])
                for each in student_id:
                    student_id.remove(each)
                    if len(student_id) == 1:
                        student_id.pop()
        if(len(student_id)) == 0:
            stopgrouping = 2

    for i in range(len(Group)):
        k = i+1
        for each in Group[i]:
            #将分组信息写入数据库
            query = ('update pa_student_course set auto_group_id = %s where student_id = %s')
            cursor.execute(query, (k, each))
            conn.commit()
            query2 = ('update pa_student_course set grouptype = %s where student_id = %s')
            cursor.execute(query2, (1, each))
            conn.commit()

    cursor.close()


randomgrouping(sys.argv[1])








