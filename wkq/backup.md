- WSL 사용자 비밀번호 재설정
```Powershell
> wsl -u root
```
```bash
$ passwd 사용자계정
```

- ubuntu 사용자 추가
```bash
sudo adduser ubuntu && usermod -aG sudo ubuntu && su - ubuntu
```