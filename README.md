## 介绍
一款调用Wget开发的仿站HTTP接口.
## 需求
1. 环境:PHP7,Wget
2. PHP函数:exec
## 参数
### 请求
名称|必填|说明
---|---|---
url|是|网址
### 响应
名称|说明
---|---
state|状态码
information|信息
#### 响应(information)
名称|说明
---|---
preview_url|预览网址
download_url|下载网址
