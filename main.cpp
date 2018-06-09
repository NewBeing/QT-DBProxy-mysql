
#include "activerecord.h"
#include <QApplication>

int main(int argc, char *argv[])
{
    QApplication a(argc, argv);

    ActiveRecord *DB = new ActiveRecord();

    QVector<QString> field ;
    field.push_back("id");
    field.push_back("lc_id");
    //field.push_back("*");
    qDebug()<<field;
    QMap<QString,QString> cond;
    QMap<QString,QString> update;
    //cond["id"] = "63";
    cond["lc_id"] = "=53";
    cond["lc_name"] = "=\'ff\'";
    update["lc_address"] = "addr";
    update["city_id"] = "22";
    QString tab = "test";
    DB->selectRows(field, cond, tab);
    //DB->insertRows(tab, cond);
    //DB->updateRows(update, cond, tab);

    delete DB;
    return a.exec();
}

