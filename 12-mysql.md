# 개발환경 구축 (Ubuntu 24.04 + MySQL)

## 📘 학습 개요

Ubuntu에서 MySQL 설치 및 설정하기

## 💡 주요 내용

- MySQL 이란?

- MySQL 서버 설치 및 설정

- MySQL 접속 및 사용자 추가

---

# 1. MySQL 이란?
> MySQL은 관계형 데이터베이스 관리 시스템(RDBMS) 중 하나로, 전 세계에서 가장 많이 사용되는 오픈소스 데이터베이스입니다. 간단히 말해, 데이터를 테이블(Table) 형태로 저장하고 관리하는 소프트웨어입니다.

## 1-1. 주요 특징

| 특징                     | 설명                                                          |
| ---------------------- | ----------------------------------------------------------- |
| **관계형 데이터베이스 (RDBMS)** | 데이터를 테이블(TABLE) 구조로 저장하고, 테이블 간 관계를 설정할 수 있음.                   |
| **SQL 사용**             | `SELECT`, `INSERT`, `UPDATE`, `DELETE` 같은 SQL 언어로 데이터를 조작함. |
| **오픈소스**               | 무료로 사용할 수 있고, 소스코드도 공개되어 있음. (현재는 Oracle이 관리)               |
| **속도와 안정성**            | 웹 서비스나 애플리케이션에서 빠르고 안정적으로 작동.                               |
| **다양한 운영체제 지원**        | Windows, Linux, macOS 등에서 사용 가능.                            |
| **서버-클라이언트 구조**        | MySQL 서버에 접속해서 데이터를 주고받는 방식.                                |

## 1-2. 왜 많이 쓰일까?

1. 무료 + 설치 간단 + 속도 빠름

2. WordPress, 카카오톡 게임, 네이버 카페 등도 내부적으로 MySQL 사용

3. JDBC, Python, PHP 등 다양한 언어와 쉽게 연결 가능


## 1-3. MySQL 서버–클라이언트 구조 

> 클라이언트는 SQL을 보내고, MySQL Server는 처리하고 결과를 돌려준다.

| 구분                      | 역할                          | 하는 일                                         | 예시 프로그램                                                                    |
| ----------------------- | --------------------------- | -------------------------------------------- | -------------------------------------------------------------------------- |
| **MySQL 클라이언트(Client)** | MySQL Server에 **SQL 요청 전송** | `SELECT`, `INSERT`, `UPDATE` 등 명령 전달 → 결과 확인 | MySQL Workbench, DBeaver, VSCode SQL, `mysql` CLI, 웹/앱 서버(Node.js, Java 등) |
| **MySQL 서버(Server)**    | SQL 처리 & 데이터 관리             | SQL 실행, 데이터 저장, 트랜잭션/인덱스 처리, 사용자 권한 관리       | `mysqld` (MySQL Server 엔진)                                                 |

--- 

# 2. MySQL 설치 및 설정

## 2-1. MySQL 설치 ( with APT )
| 명령어               | 의미                               |
| ---------------- | -------------------------------- |
| **sudo**         | 관리자(root) 권한으로 실행     |
| **`apt`**          | Ubuntu 패키지 관리 도구 (설치/삭제/업데이트 담당) |
| **install**      | 패키지를 설치한다                       |
| **-y**           | 모두 "설치하시겠습니까?" 질문에 자동으로 Yes 처리   |
| **mysql-server** | 설치할 MySQL 서버 패키지 이름              |

```bash
sudo apt install -y mysql-server
```

## 2-2. MySQL 서비스 상태 확인
| 명령어             | 설명                    |
| ------------- | --------------------- |
| **sudo**      | 관리자(root) 권한으로 실행            |
| **`systemctl`** | systemd 서비스 관리 도구     |
| **status**    | 서비스 상태를 조회            |
| **mysql**     | 서비스 이름(mysql.service) |

```bash
sudo systemctl status mysql
```
```
● mysql.service - MySQL Community Server
     Loaded: loaded (/usr/lib/systemd/system/mysql.service; enabled; preset: enabled)
     Active: active (running) since Sun 2025-11-30 18:36:55 KST; 1h 24min ago
    Process: 150 ExecStartPre=/usr/share/mysql/mysql-systemd-start pre (code=exited, status=0/SUCCESS)
   Main PID: 230 (mysqld)
     Status: "Server is operational"
      Tasks: 37 (limit: 18634)
     Memory: 380.9M (peak: 440.2M)
        CPU: 13.441s
     CGroup: /system.slice/mysql.service
             └─230 /usr/sbin/mysqld
```


## 2-4. MySQL 초기 설정

> MySQL 설치 후 초기 보안 설정을 수행합니다. `mysql_secure_installation` 명령어를 사용하여 루트 사용자 비밀번호를 설정하고, 불필요한 기본 설정을 제거할 수 있습니다.

```bash
sudo mysql_secure_installation
``` 

1. 비밀번호 정책 설정 : `n` ( `n` 하면 비밀번호 자유롭게 설정가능. `y` 하면 비밀번호 설정할때 대소문자랑 특수문자 강제됨 )

2. 설치시 자동으로 익명 사용자(anonymous user) 계정을 생성했는데 삭제할지 여부 : `y` ( 삭제하는게 좋음 )

3. MySQL의 root 계정이 원격(리모트)에서 접속하지 못하도록 막을 건지 묻는 단계입니다: `y` ( 보안상 무조건 `y` )

4. 기본으로 test 데이터베이스가 생성이 되어있는데 삭제할건지 묻는 단계 : `y` ( MySQL 8.0부터는 기본적으로 test DB가 없기 때문에, 아무거나 해도 상관없음 )

5. 지금까지 설정한 사용자 권한 변경 내용들을 바로 적용할지 물어보는 단계 : `y`

## 2-5. MySQL 포트 변경 ( 3306 -> 3308 )

> 포트를 변경하려면 `/etc/mysql/mysql.conf.d/mysqld.cnf` 파일을 수정해야함.


### 포트 변경하는 이유 ?

- “기본 3306 포트가 이미 사용 중일 때”

- “여러 버전의 MySQL을 같이 쓰고 싶을 때”

### 1) 설정파일이 들어있는 폴더를 홈디렉터리에서 편집할수 있도록 링크 생성:

| 부분                         | 의미                                                        |
| -------------------------- | --------------------------------------------------------- |
| `ln`                       | 파일/디렉토리 **링크(link)** 를 만드는 명령어                            |
| -s                       | **심볼릭 링크(symbolic link)** 생성 옵션<br>→ 바로가기(Shortcut) 같은 개념 |
| /etc/mysql/mysql.conf.d/ | **원본 디렉토리** (MySQL 설정 파일들이 들어 있는 경로)                      |
| ~/                       | **링크를 만들 위치** (사용자 홈 디렉토리)                                |
```bash
ln -s /etc/mysql/mysql.conf.d/ ~/
```


### 2) mysqld.cnf 파일을 편집할수 있도록 소유자 변경:

| 요소                          | 의미                                                              |
| --------------------------- | --------------------------------------------------------------- |
| sudo                      | 관리자(root) 권한으로 실행                                               |
| `chown`                     | **파일/디렉토리의 소유자(owner)를 변경**하는 명령어                               |
| ubuntu                    | 바꿀 **새로운 소유자 계정 이름**                                            |
| ~/mysql.conf.d/mysqld.cnf | 소유자를 변경할 **대상 파일 경로**<br>(홈 디렉토리 안에 있는 mysqld.cnf 심볼릭 링크 또는 파일) |


```bash
sudo chown ubuntu ~/mysql.conf.d/mysqld.cnf
```

### 3) ~/mysql.conf.d/mysqld.cnf 파일 수정

| 항목           | 설명                                                         |
| ------------ | ---------------------------------------------------------- |
| **역할**       | MySQL 서버(mysqld)가 동작할 때 필요한 **각종 설정값(Configuration)** 을 정의 |
| **위치**       | /etc/mysql/mysql.conf.d/mysqld.cnf (Ubuntu 기본)           |
| **누가 읽음?**   | MySQL 데몬(mysqld)이 서비스 시작 시 자동으로 읽어들임                     |
| **주요 포함 내용** | 포트 번호, bind-address, 데이터 디렉토리, 로그 경로, 버퍼·캐시 설정 등           |
| **변경 필요 상황** | 포트 변경, 외부 접속 허용, 성능 튜닝, 로그 설정, 보안 정책 변경                    |
| **변경 후**     | 반드시 `systemctl` restart mysql 필요                           |


### 설정파일 내용에서 #port = 3306 -> port = 3308 로 변경:
```
#
# The MySQL database server configuration file.
#
# One can use all long options that the program supports.
# Run program with --help to get a list of available options and with
# --print-defaults to see which it would actually understand and use.
#
# For explanations see
# http://dev.mysql.com/doc/mysql/en/server-system-variables.html

# Here is entries for some specific programs
# The following values assume you have at least 32M ram

[mysqld]
#
# * Basic Settings
#
user		= mysql
# pid-file	= /var/run/mysqld/mysqld.pid
# socket	= /var/run/mysqld/mysqld.sock
port		= 3308
# datadir	= /var/lib/mysql
```

### 저장 후 리눅스 쉘에서 아래 명령어 실행:
| 부분          | 설명                                       |
| ----------- | ---------------------------------------- |
| sudo      | 관리자(root) 권한으로 실행    |
| `systemctl` | systemd의 서비스 관리 명령어. 서비스 시작/중지/상태 확인에 사용 |
| restart   | 서비스 **재시작(restart)** 하라는 의미              |
| mysql     | 재시작할 서비스 이름(MySQL 서버 데몬)                 |

```bash
sudo systemctl restart mysql
```



--- 

# 3. MySQL 접속 및 사용자 추가

## 3-1. MySQL CLI(콘솔) 접속
| 요소        | 설명                  |
| --------- | ------------------- |
| **sudo**  | 관리자(root) 권한으로 실행   |
| **`mysql`** | MySQL 클라이언트 프로그램 실행 |

```bash
sudo mysql
```

- 왜 비밀번호 없이 접속이 될까?   
  Ubuntu 공식 MySQL 패키지(mysql-server)는 root 계정 인증 방식을 auth_socket(socket 인증) 으로 설정하기 떄문.

- auth_socket 인증이란?   
  리눅스의 root 인증을 그대로 믿고 MySQL root 로 “문 열어주는 방식”

## 3-2. MySQL CLI(콘솔) 종료 
```sql
exit;
```

## 3-3. 사용자 만들기

### 1) 계정 생성
| 요소                       | 의미                        |
| ------------------------ | ------------------------- |
| **`CREATE USER`**          | 새로운 MySQL 계정 생성           |
| **'test'**                 | 만들고 싶은 사용자명               |
| **@'localhost'**         | 이 계정이 접속할 수 있는 호스트 |
| **IDENTIFIED BY 'test123'** | 이 계정의 비밀번호 설정             |

```sql
CREATE USER 'test'@'localhost' IDENTIFIED BY 'test123';
```

### 2) 계정 생성확인
```sql
select user, host from mysql.user;
```
```
+------------------+-----------+
| user             | host      |
+------------------+-----------+
| debian-sys-maint | localhost |
| mysql.infoschema | localhost |
| mysql.session    | localhost |
| mysql.sys        | localhost |
| root             | localhost |
| test             | localhost |
+------------------+-----------+
```

### MySQL 계정은 “user + host” 조합으로 식별됨
| 컬럼       | 의미                 | 예시                         |
| -------- | ------------------ | -------------------------- |
| **user** | MySQL 계정 이름        | root, test    |
| **host** | 접속을 허용할 위치(출발지 IP) | localhost, %, 192.168.0.10 |

```bash
'user'@'host'
```

즉, `user` + `host` 합쳐서 “계정 1개”라고 취급됨.

## 3-4. 데이터베이스 생성
| 요소                | 설명                      |
| ----------------- | ----------------------- |
| `CREATE DATABASE` | 새로운 데이터베이스를 생성하는 SQL 명령 |
| test            | 생성할 데이터베이스 이름           |

```sql
CREATE DATABASE testdb;
```

## 3-5. 권한 주기
| 부분                      | 설명                                                         |
| ----------------------- | ---------------------------------------------------------- |
| `GRANT`                 | 특정 사용자에게 권한을 **부여**하는 명령                                   |
| ALL PRIVILEGES        | SELECT, INSERT, UPDATE, DELETE, CREATE, DROP 등 **모든 권한**   |
| `ON` test.*             | 대상은 **test 데이터베이스(test) 안의 모든 테이블(*)**                     |
| `TO` 'test'@'localhost' | 권한을 받을 사용자: **test 계정(로컬 접속)**                             |
| `WITH GRANT OPTION`     | 자신이 가진 test DB의 권한을 **다른 사용자에게도 부여(GRANT)** 할 수 있는 능력까지 부여 |

```sql
GRANT ALL PRIVILEGES ON testdb.* TO 'test'@'localhost';
```

권한확인:
```sql
SHOW GRANTS FOR 'test'@'localhost';
```

## 3-6. 생성된 사용자 및 권한 즉시적용
> MySQL은 사용자 계정/권한 정보를 메모리에 캐싱하고 있음. 그래서 변경 사항을 즉시 반영하기 위해 아래 명령어를 사용함.

| 요소             | 설명                              |
| -------------- | ------------------------------- |
| **`FLUSH`**      | 메모리에 로드된 권한 정보를 초기화/재적재         |
| **PRIVILEGES** | 사용자 계정/권한 테이블(mysql.user 등)을 의미 |
```sql
FLUSH PRIVILEGES;
```

## (참고) 비밀번호 변경
| 부분                          | 설명                 |
| --------------------------- | ------------------ |
| **`ALTER USER`**              | 기존 사용자 계정을 수정      |
| **'test'@'localhost'**      | 수정할 계정(이름 + 접속 위치) |
| **`IDENTIFIED BY` 'test123'** | 새로운 비밀번호 설정        |

```sql
ALTER USER 'test'@'localhost' IDENTIFIED BY 'test123';
```

## (참고) 계정삭제
| 부분                     | 설명                      |
| ---------------------- | ----------------------- |
| **`DROP USER`**          | 사용자 계정을 삭제하는 명령어        |
| **'test'@'localhost'** | 삭제할 계정(사용자 이름 + 접속 호스트) |
```sql
DROP USER 'test'@'localhost';
```        

## (참고) 비밀번호 정책 확인 및 완화
> 비밀번호 정책 때문에 변경이 안될 때는 다음을 확인하세요:
```sql
SHOW VARIABLES LIKE 'validate_password%';
```
```sql
SET GLOBAL validate_password.policy = LOW;
```
```sql
SET GLOBAL validate_password.length = 4;
```


--- 


# 🧩 실습 / 과제
### 1. MySQL 상태 확인 & 서비스 재시작 테스트
```  
sudo systemctl status mysql

sudo systemctl stop mysql

sudo systemctl start mysql

sudo systemctl restart mysql
```

### 2. MySQL CLI(콘솔) 접속 + 사용자 계정 생성 + DB 생성 + 워크벤치 커넥션 생셩
```bash
sudo mysql
```

```sql
use mysql
```

```sql
CREATE USER 'user'@'localhost' IDENTIFIED BY 'user123';
```

```sql
CREATE DATABASE userdb;
```

```sql
GRANT ALL PRIVILEGES ON userdb.* TO 'user'@'localhost';
```

```sql
SHOW GRANTS FOR 'user'@'localhost';
```

```sql
FLUSH PRIVILEGES;
```