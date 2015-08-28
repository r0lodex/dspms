# DSPMS
Dead Simple Parcel Management System

### Description
a quick basic api for basic operation of PMS

backend: using Horus.php framework mainly for routing purpose

frontend: tba

### API LIST

##### user login
>POST | /api/login

request:

username : system

password : system
```
/api/login
```
response:
```json
{"id":"1","token":"576d045d14c3a741dbb3421f03ef27d6"}
```


##### user read all
>GET | /api/user

request
```
/api/user?uid=1&token=576d045d14c3a741dbb3421f03ef27d6 
```
response
```json
[{"id":"4","username":"admin"},{"id":"5","username":"admin2"}]
```

##### user create
> POST | /api/user
to create system user (non student)

student is system consumer


request

username : <username>

password : <password>
```
/api/user
```

response:

new user created!

##### user update password
>POST | /api/user/{id}
request:

password: <new password>

```
/api/user/5?uid=1&token=576d045d14c3a741dbb3421f03ef27d6
```

response:
```json
{"msg":"password update successful!"}
```
##### user DELETE all
>DELETE | /api/user/{id}
request:
```
/api/user/5?uid=1&token=576d045d14c3a741dbb3421f03ef27d6
```

response:
```json
{"msg":"user deleted!"}
```
