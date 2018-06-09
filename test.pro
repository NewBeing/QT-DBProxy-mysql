#-------------------------------------------------
#
# Project created by QtCreator 2018-06-04T13:06:30
#
#-------------------------------------------------

QT       += core gui
QT       += sql

greaterThan(QT_MAJOR_VERSION, 4): QT += widgets

TARGET = test
TEMPLATE = app


SOURCES += main.cpp\
    activerecord.cpp \
    db.cpp

HEADERS  += \
    activerecord.h \
    db.h

FORMS    +=
