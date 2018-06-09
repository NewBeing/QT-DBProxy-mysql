#include "db.h"
#include <QDebug>
#include <iostream>
DB::DB(){
    this->d = QSqlDatabase::addDatabase("QMYSQL");
    this->d.setHostName("58.87.95.69");
    this->d.setDatabaseName("mysql");
    this->d.setPort(3306);
    this->d.setUserName("root");
    this->d.setPassword("password");
    if(this->d.open())
        qDebug()<<"Hi mysql!连接";
    else
        qDebug()<<"失败"<<endl;
    qDebug()<<QSqlDatabase::drivers()<<endl;
}
DB::~DB(){
    this->d.close();
    qDebug()<<"db is closed";
}
void DB::connectDB(){
    this->d = QSqlDatabase::addDatabase("QMYSQL");
    this->d.setHostName("58.87.95.69");
    this->d.setDatabaseName("mysql");
    this->d.setPort(3306);
    this->d.setUserName("root");
    this->d.setPassword("password");
    if(this->d.open())
        qDebug()<<"Hi mysql!连接";
    else
        qDebug()<<"失败"<<endl;
    qDebug()<<QSqlDatabase::drivers()<<endl;
}
void DB::connectDB(QString hostname, int port, QString DBname, QString u, QString p){

    QSqlDatabase db = QSqlDatabase::addDatabase("QMYSQL");
    db.setHostName(hostname);
    db.setPort(port);
    db.setDatabaseName(DBname);
    db.setUserName(u);
    db.setPassword(p);
    if(db.open())
    {
        qDebug()<< "opened successfully";
        qDebug()<< db.driverName();
    }
    else
    {
        qDebug() << "opened error";

    }
}
 void DB::disconDB()
 {
     this->d.close();
     qDebug()<<"db is closed";
 }
