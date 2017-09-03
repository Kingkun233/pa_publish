import pymysql
import numpy as np
import sys
def record(class_id):
    def distEclud(vecA, vecB):
        return np.sqrt(sum(np.power(vecA - vecB, 2)))
    def getpm(class_id):
        conn = pymysql.connect(user='root', password='111111', database='pa', charset='utf8')
        cursor = conn.cursor()
        query1 = ('select student_id, pre_course_score,aim_score from pa_student_course where class_id=%s')
        cursor.execute(query1, class_id)
        student = []
        student_id = []
        for each in cursor:
            student.append(list(each))
            student_id.append(each[0])
        student_attribute = []
        for each in student_id:
            query2 = ('select * from pa_attribute where student_id = %s')
            cursor.execute(query2, each)
            for ele in cursor:
                student_attribute.append(list(ele[1:]))
        pm = []
        for i in range(len(student)):
            pm.append([])
            pm[i] = student[i] + student_attribute[i][1:]
        return pm
    def target_grouping(pm):
        # 拿出聚类属性：
        dp = []
        for i in range(len(pm)):
            dp.append([])
            dp[i].append(pm[i][0])
            dp[i].append(pm[i][1])
            dp[i].append(pm[i][2])

        # 先分为4组
        k = 4
        # 先选钱四个点作为簇心
        cluster = [dp[0], dp[1], dp[2], dp[3]]
        # 初始化簇
        clusterchange = True
        # 开始聚类
        m = 0
        while clusterchange:
            clust = []
            for i in range(4):
                clust.append([])
            for each in dp:
                distance = []
                for ele in cluster:
                    distance.append(distEclud(np.array(each[1:]), np.array(ele[1:])))
                distance2 = sorted(distance)
                k = distance.index(distance2[0])
                clust[k].append(each)
            newcluster = []
            for each in clust:
                newcluster2 = [0, 0, 0]
                for element in each:
                    newcluster2 = np.array(newcluster2) + np.array(element)
                newcluster2 = newcluster2 / len(each)
                newcluster.append(newcluster2.tolist())
            # 对比新簇心和旧簇心得变化，如果和上一次对比变化太大，继续聚类
            pdis = []
            for i in range(4):
                pdis.append(distEclud(np.array(cluster[i][1:]), np.array(newcluster[i][1:])))
            for i in range(len(pdis)):
                if pdis[i] < 0.1:
                    newcluster[i] = cluster[i]
                else:
                    continue
            if newcluster == cluster:
                clusterchange = False

            else:
                cluster = newcluster[:]
        return clust
    def dif_grouping(same_grouping, class_id):
        pm = getpm(class_id)
        # 整理传过来的分组
        pre_dif_grouping = []
        # for i in range(len(same_grouping)):
        #     pre_dif_grouping.append([])
        #     for each in same_grouping[i]:
        #         pre_dif_grouping[i].append(pm[int(each[0])])
        for i in range(len(same_grouping)):
            pre_dif_grouping.append([])
            for each in same_grouping[i]:
                k = each[0]
                for elements in pm:
                    if elements[0] == each[0]:
                        pre_dif_grouping[i].append(elements)

        # 最终分组
        dg = []
        for elements in pre_dif_grouping:
            if len(elements) <= 4:
                dg.append(elements)
            while len(elements) > 4:
                per_dg = []
                stopadding = True
                while stopadding:
                    if len(per_dg) == 0:
                        per_dg.append(elements[0])
                        elements.pop(0)
                    # 计算per_dg中的平均位置
                    arg1 = []
                    for i in range(len(per_dg[0])):
                        arg1.append(0)
                    for ele in per_dg:
                        arg1 = np.array(arg1) + np.array(ele)
                    arg1 = arg1 / len(per_dg)
                    distance = []
                    for element in elements:
                        distance.append(distEclud(np.array(arg1[1:]), np.array(element[1:])))
                    distance2 = sorted(distance)
                    k = distance.index(distance2[-1])
                    per_dg.append(elements[k])
                    elements.pop(k)
                    if len(per_dg) == 4:
                        dg.append(per_dg)
                        stopadding = False
                        per_dg = []
                if len(elements) <= 4:
                    dg.append(elements)

        finalgrouping = []
        waittogroup = []
        for each in dg:
            if len(each) == 4:
                finalgrouping.append(each)
            else:
                waittogroup.append(each)
        if len(waittogroup) < 4:
            perfinalgrouping = []
            for each in waittogroup:
                for ele in each:
                    perfinalgrouping.append(ele)
            finalgrouping.append(perfinalgrouping)
        if len(waittogroup) == 4:
            perfinalgrouping = []
            for ele in waittogroup[0]:
                perfinalgrouping.append(ele)
            for ele2 in waittogroup[1]:
                perfinalgrouping.append(ele2)
            perfinalgrouping2 = []
            for e in waittogroup[2]:
                perfinalgrouping2.append(e)
            for individual in waittogroup[3]:
                perfinalgrouping2.append(individual)
            finalgrouping.append(perfinalgrouping)
            finalgrouping.append(perfinalgrouping2)
        final_g = []
        for i in range(len(finalgrouping)):
            final_g.append([])
            for each in finalgrouping[i]:
                final_g[i].append(int(each[0]))
        if len(final_g[-1]) == 1:
            final_g[-2].append(final_g[-1][0])
            final_g.pop(-1)
        if len(final_g[-1]) == 2:
            final_g[-2].append(final_g[-1][0])
            final_g[-3].append(final_g[-1][1])
            final_g.pop(-1)
        return final_g
    final_g = dif_grouping(target_grouping(getpm(class_id)), class_id)
    conn = pymysql.connect(user='root', password='111111', database='pa', charset='utf8')
    cursor = conn.cursor()
    for i in range(len(final_g)):
        k = i+1
        for each in final_g[i]:
            query = ('update pa_student_course set system_group_id = %s where id = %s')
            cursor.execute(query, (k, each))
            conn.commit()
            query2 = ('update pa_student_course set grouptype = %s where student_id = %s')
                        cursor.execute(query2, (2, each))
                        conn.commit()
    cursor.close()
    conn.close()
record(sys.argv[1])