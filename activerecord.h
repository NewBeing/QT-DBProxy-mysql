#ifndef ACTIVERECORD_H
#define ACTIVERECORD_H
#include "db.h"
#include <QObject>
#include <QDebug>
#include <QtSql/QSqlError>
#include <QtSql/QSqlQuery>
#include <QVector>
#include <QMap>
#include <QString>

class ActiveRecord : public QObject
{
    Q_OBJECT
public:
    explicit ActiveRecord(QObject *parent = 0);
    ~ActiveRecord();
    bool OpenDataBase();
    QVector<QMap<QString,QString>> selectRows(QVector<QString> feilds, QMap<QString,QString> conds, QString table);
    bool insertRows(QString table, QMap<QString,QString> insertValues);
    bool updateRows(QMap<QString,QString> updateValues, QMap<QString,QString> conds, QString table);
private:
    DB *DBProxy;
    QString SPACE = " ";
    QString COMMA = " , ";
    QString AND = " and ";
    QString FROM = " from ";
    QString WHERE = " where ";
    QVector<QString> test;//table test
    QVector<QString> admin_config;//table
    QVector<QString> room_authen;//table

signals:

public slots:
};

#endif // ACTIVERECORD_H
