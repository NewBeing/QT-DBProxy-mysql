#include "activerecord.h"

/*
construct function
connect database
*/
ActiveRecord::ActiveRecord(QObject *parent) : QObject(parent) {
    this->DBProxy = new DB();
    this->test.push_back("id");
    this->test.push_back("lc_id");
    this->test.push_back("lc_name");
    this->test.push_back("lc_address");
    this->test.push_back("lc_lng");
}

/*
deconstruct function
disconnect from database
*/
ActiveRecord::~ActiveRecord() {
    delete DBProxy;
}

/*
 * QVector<QString> fields          查找字段
 * QMap<QString,QString> conds      查找条件
 * QString table                    查找的表
 * return: QVector<QMap<QString,QString>>
*/
QVector<QMap<QString,QString>> ActiveRecord::selectRows(QVector<QString> fields, QMap<QString,QString> conds, QString table){
    QString q = "select ";
    QString strFields;
    QString strConds;

    //dealing with fields
    QString f;
    for (int i = 0; i < fields.size(); i++){
        strFields += fields[i];
        if(i != fields.size() - 1){
            strFields += this->COMMA;
        }
    }
    qDebug()<<"strFields:\n"<<strFields;
    q += strFields;//fields
    q += this->FROM;
    q += table;//table
    if(conds.size() != 0){
        q += this->WHERE;
    }
    qDebug()<<"q:\n"<<q;
    //dealing with conds
    QMap<QString,QString>::const_iterator i;
    for(i = conds.constBegin(); i != conds.constEnd(); i++){
        strConds += i.key();
        strConds += i.value();
        if(i != conds.constEnd() - 1){
            strConds += this->AND;
        }
    }
    q += strConds;
    qDebug()<<"q:\n"<<q;
    //execute sql
    QSqlQuery query;
    QVector<QMap<QString,QString>> finalResult;
    QMap<QString,QString> tempResult;
    QString strKey;
    QString strValue;
    int columns = 0;
    query.exec(q);
    while(query.next()){

        if (table == "test") {
            if(fields[0] == "*"){
                columns = this->test.size();
            }else{
                columns = fields.size();
            }
        }
        if(table == "admin_config") {
            if(fields[0] == "*"){
                columns = this->admin_config.size();

            }else{
                columns = fields.size();
            }
        }
        if(table == "room_authen") {
            if(fields[0] == "*"){
                columns = this->room_authen.size();
            }else{
                columns = fields.size();
            }
        }
        for(int i = 0; i < columns; i++) {
            qDebug()<<"query.size()"<<query.size();
            qDebug()<<"columns:\n"<<columns;
            //set key
            if (table == "test") {
                if(fields[0] == "*"){
                    strKey = this->test[i];
                    qDebug()<<"this->test[i]"<<this->test[i];
                }else{
                    strKey = fields[i];
                }
            }
            if(table == "admin_config") {
                if(fields[0] == "*"){
                    strKey = this->admin_config[i];

                }else{
                    strKey = fields[i];
                }
            }
            if(table == "room_authen") {
                if(fields[0] == "*"){
                    strKey = this->room_authen[i];
                }else{
                    strKey = fields[i];
                }
            }
            qDebug()<< "strKey:" <<strKey;
            //set value
            strValue = query.value(i).toString();//
            qDebug()<< "strValue:" <<strValue;
            //set map
            tempResult[strKey] = strValue;
        }
        finalResult.push_back(tempResult);
        tempResult.clear();
    }
    return finalResult;
}

/*
 * QString table                        插入的表
 * QMap<QString,QString> insertValues   插入的字段和数据
 * return：bool
 *
*/
bool ActiveRecord::insertRows(QString table, QMap<QString,QString> insertValues){
    QString q = "insert ";
    q += table;
    q += " (`";
    qDebug()<<"q:\n"<<q;
    QString fields;
    QString values;
    QMap<QString,QString>::const_iterator i;
    for(i = insertValues.constBegin(); i != insertValues.constEnd(); i++){
        fields += i.key();
        qDebug()<<"i.key()"<<i.key();
        fields += "`";
        values += i.value();
        qDebug()<<"i.value()"<<i.value();
        if(i != insertValues.constEnd() - 1){
            fields += this->COMMA;
            fields += "`";
            values += this->COMMA;
        }
    }
    q += fields;
    q += ") value (";
    q += values;
    q += ")";
    qDebug()<<"q:\n"<<q;
    //execute sql
    QSqlQuery query;
    query.exec(q);
    if((query.lastError().isValid())){
        return false;
    }
    qDebug()<<query.lastError();
    return true;
}

/*
 *QMap<QString,QString> updateValues 更新字段
 *QMap<QString,QString> conds        更新条件
 *QString table                      更新表
 *return:bool
 */
bool ActiveRecord::updateRows(QMap<QString,QString> updateValues, QMap<QString,QString> conds, QString table){
    QString q = "update ";
    QString strUpdateValues;
    QString strConds;
    q += table;
    if(updateValues.size() != 0){
        q += " set ";
    }
    //dealing with UpdateValues
    QMap<QString,QString>::const_iterator i;
    qDebug()<<"updateValues:\n"<<updateValues;
    for(i = updateValues.constBegin(); i != updateValues.constEnd(); i++){
        strUpdateValues += i.key();
        strUpdateValues += "=";
        strUpdateValues += i.value();
        if(i != (updateValues.constEnd() - 1)){
            strUpdateValues += this->COMMA;
        }
    }
    qDebug()<<"strUpdateValues:\n"<<strUpdateValues;
    q += strUpdateValues;//fields
    if(conds.size() != 0){
        q += this->WHERE;
    }
    qDebug()<<"q:\n"<<q;
    //dealing with conds
    for(i = conds.constBegin(); i != conds.constEnd(); i++){
        strConds += i.key();
        strConds += i.value();
        if(i != conds.constEnd() - 1){
            strConds += this->AND;
        }
    }
    q += strConds;
    qDebug()<<"q:\n"<<q;
    //execute sql
    QSqlQuery query;
    if((query.lastError().isValid())){
        return false;
    }
    qDebug()<<query.lastError();
    return true;
}
