#ifndef DB_H
#define DB_H
#include <QString>
#include <QtSql/QSqlDatabase>
class DB
{
public:
    DB();
    ~DB();
    void connectDB(QString hostname, int port, QString DBname, QString u, QString p);
    void connectDB();
    void disconDB();

private:
    QSqlDatabase d;
};

#endif // DB_H
