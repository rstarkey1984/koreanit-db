- /etc/wsl.conf
```
sudo sh -c 'cat > /etc/wsl.conf << "EOF"
[boot]
# ssh 등 systemd 서비스 사용하려면 권장
systemd=true           

[user]
# WSL 접속 시 자동 로그인할 리눅스 계정
default=ubuntu

[automount]
# /mnt/c 같은 자동 마운트 중지
enabled=false          

[interop]
# 윈도우 exe 실행 금지
enabled=false          
# 윈도우 PATH 섞기 끄기
appendWindowsPath=false 

[network]
# WSL Ubuntu 에서 사용할 hostname  
hostname=ubuntu22 
EOF'
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

✅ 1. 리스너 시작(Start)
lsnrctl start


Oracle Database가 밖에서 접속받으려면 반드시 리스너가 떠 있어야 함.

✅ 2. 리스너 종료(Stop)
lsnrctl stop

✅ 3. 리스너 상태 확인(Status)
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
