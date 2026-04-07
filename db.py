import pymysql

def get_connection():
    return pymysql.connect(
        host="r2.local",
        user="facturacion",
        password="juanmanuel",
        database="facturacion",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=False
    )