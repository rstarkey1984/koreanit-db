# 4. MySQL 워크벤치 설정

## Database 메뉴에서 Manage Connections 선택

1. 왼쪽아래 `[ New ]` 버튼 클릭

2. `Connection Name` : wsl-ubuntu24

3. `Hostname` : 127.0.0.1

4. `Port` : 3308

5. `Username` : test

6. `Password` : [ Store in Vault ] 클릭해서 비밀번호 test123 입력 후 엔터

7. `Default Schema` : testdb

## 참고) MySQL Workbench 관리자 권한으로 모든 기능 쓰기
```bash
sudo mysql
```
```sql
CREATE USER 'admin'@'localhost' IDENTIFIED BY 'admin123';
```
```sql
GRANT ALL PRIVILEGES ON *.* TO 'admin'@'localhost' WITH GRANT OPTION;
```

1. Database 메뉴에서 Manage Connections 선택

2. 왼쪽아래 `[ New ]` 버튼 클릭

3. `Connection Name` : wsl-ubuntu24

4. `Connection Method` : Standard TCP/IP over SSH 선택

## 4-1. Connection - Parameters 탭에서

1. `SSH Hostname` : localhost:2222

2. `SSH Username` : ubuntu

3. `SSH Key File` : 비공개키 선택 ( 예: `C:\Users\사용자\.ssh\myfirstkey` )

4. `MySQL Hostname` : 127.0.0.1

5. `MySQL Server Port` : 3308

6. `Username` : test

7. `Password` : [ Store in Vault ] 클릭해서 비밀번호 test123 입력 후 엔터

8. `Default Schema` : testdb

## 4-2. Remote Management

1. `SSH login based management` 선택

2. `Hostname` : localhost

3. `Username` : ubuntu

4. `Authenticate Using SSH Key` : 체크

5. `SSH Key Path` : 비공개키 선택 ( 예: `C:\Users\사용자\.ssh\myfirstkey` )

## 4-3. System Profile

1. `System Type` : Linux

2. `Configuration File` : /etc/mysql/mysql.conf.d/mysqld.cnf

3. `Start MySQL` : sudo systemctl start mysql

4. `Stop MySQL` : sudo systemctl stop mysql


- mysql sys 스키마 날렸을때,

```
# 백업
sudo mysqldump --all-databases --routines --events > alldb_backup.sql

# 초기화
sudo apt purge -y mysql-server mysql-client mysql-common
sudo rm -rf /var/lib/mysql
sudo rm -rf /etc/mysql
sudo apt autoremove -y

# 재설치
sudo apt install -y mysql-server

# 복구
sudo mysql < alldb_backup.sql
```

- test 사용자 추가
```bash
sudo adduser test && sudo usermod -aG sudo test && sudo su - test
```

- test 사용자 삭제
```bash
sudo userdel -r test
```   

- WSL 포트포워딩 확인:
```powershell
> netsh interface portproxy show v4tov4
```

- Oracle Linux에서 systemctl 자동완성 켜는 방법  

```bash
sudo dnf install -y bash-completion
```
> ~/.bashrc.d/bash_completion
```bash
. /usr/share/bash-completion/bash_completion
```


- 오라클 리스너 설정 파일 수정
> ~/.bashrc.d/oracle
```bash
export ORACLE_HOME=/opt/oracle/product/26ai/dbhomeFree
export ORACLE_SID=FREE
export PATH=$ORACLE_HOME/bin:$PATH
```

```bash  
vim $ORACLE_HOME/network/admin/listener.ora  
```

```
LISTENER =
  (DESCRIPTION_LIST =
    (DESCRIPTION =
      (ADDRESS = (PROTOCOL = TCP)(HOST = localhost)(PORT = 1539))
      (ADDRESS = (PROTOCOL = IPC)(KEY = EXTPROC1521))
    )
  )
```
```bash
lsnrctl start
```


- 오라클 접속
```bash
sqlplus / as sysdba
```

- systemd preinstall 자동완성 끄기
```
sudo mv /etc/systemd/system/oracle-database-preinstall-23ai-firstboot.service \
      /etc/systemd/system/oracle-database-preinstall-23ai-firstboot.service.bak
```

- systemd 서비스
```bash
sudo systemctl list-unit-files | grep -i oracle

sudo systemctl status oracle-free
sudo systemctl start oracle-free
sudo systemctl stop oracle-free
sudo systemctl enable oracle-free   # 부팅 시 자동 시작
```


- 👍 주요 기능 (무조건 알아둬야 함)

1. 리스너 시작(Start)
lsnrctl start


Oracle Database가 밖에서 접속받으려면 반드시 리스너가 떠 있어야 함.

2. 리스너 종료(Stop)
lsnrctl stop

3. 리스너 상태 확인(Status)
lsnrctl status


예시 출력:

Connecting to (DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=localhost)(PORT=1521)))
STATUS of the LISTENER
Alias                     LISTENER
Version                   TNSLSNR for Linux: Version 26.1.0.0.0 - Production
Start Date                24-NOV-2025 16:22:13
Listening Endpoints Summary...
  (DESCRIPTION=(ADDRESS=(PROTOCOL=tcp)(HOST=0.0.0.0)(PORT=1521)))
Services Summary...
  SERVICE "FREEPDB1" has 1 instance(s).


→
현재 리스너가 어떤 포트에서 서비스 받고 있는지, 어떤 DB 인스턴스를 등록했는지 출력해줌.




- 계정 생성

1) ol9에서 sysdba로 접속

  ```bash
  sqlplus / as sysdba
  ```

  들어가면 지금은 CDB(ROOT)에 있음.

  ```sql
  SHOW CON_NAME;
  ```
  -- CDB$ROOT 라고 나올 거예요.

2) PDB(FREEPDB1)로 컨테이너 바꾸기
  ```sql
  ALTER SESSION SET CONTAINER = FREEPDB1;
  SHOW CON_NAME;
  ```
  -- 이제 FREEPDB1 라고 나와야 정상

3) 여기서 바로 학생 계정 만들기
  ```sql
  CREATE USER test IDENTIFIED BY test123
    DEFAULT TABLESPACE users
    TEMPORARY TABLESPACE temp
    QUOTA UNLIMITED ON users;  

  GRANT CREATE SESSION, CREATE TABLE, CREATE VIEW, CREATE SEQUENCE TO test;
  -- 또는
  -- GRANT CONNECT, RESOURCE TO student01;
  ```

