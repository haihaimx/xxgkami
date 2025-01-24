<div align="center">

# 小小怪卡密验证系统

[![PHP Version](https://img.shields.io/badge/PHP-7.0+-blue.svg)](https://www.php.net)
[![MySQL Version](https://img.shields.io/badge/MySQL-5.7+-orange.svg)](https://www.mysql.com)
[![License](https://img.shields.io/github/license/xiaoxiaoguai-yyds/xxgkami)](https://github.com/xiaoxiaoguai-yyds/xxgkami/blob/main/LICENSE)
[![GitHub Stars](https://img.shields.io/github/stars/xiaoxiaoguai-yyds/xxgkami)](https://github.com/xiaoxiaoguai-yyds/xxgkami/stargazers)
[![GitHub Issues](https://img.shields.io/github/issues/xiaoxiaoguai-yyds/xxgkami)](https://github.com/xiaoxiaoguai-yyds/xxgkami/issues)

一个功能强大、安全可靠的卡密验证系统，支持多种验证方式，提供完整的API接口。
适用于软件授权、会员验证等场景。

</div>

## ✨ 系统特点

### 🛡️ 安全可靠
- SHA1 加密存储卡密
- 设备绑定机制
- 防暴力破解
- 多重安全验证
- 数据加密存储

### 🔌 API支持
- RESTful API接口
- 多API密钥管理
- API调用统计
- 详细接口文档
- 支持POST/GET验证
- 设备ID绑定机制

### ⚡ 高效稳定
- 快速响应速度
- 稳定运行性能
- 性能优化设计
- 支持高并发访问

### 📊 数据统计
- 实时统计功能
- 详细数据分析
- 直观图表展示
- API调用统计
- 完整使用记录

## 🚀 快速开始

### 环境要求
```bash
PHP >= 7.0
MySQL >= 5.7
Apache/Nginx
```

### 安装步骤

1. 克隆项目
```bash
git clone https://github.com/xiaoxiaoguai-yyds/xxgkami.git
```

2. 上传到网站目录

3. 访问安装页面
```
http://your-domain/install/
```

4. 按照安装向导完成配置

## 📚 使用说明

### 管理员后台
1. 访问 `http://your-domain/admin.php`
2. 使用安装时设置的管理员账号登录
3. 进入管理面板

### API调用示例
```php
// POST请求示例
$url = 'http://your-domain/api/verify.php';
$data = [
    'card_key' => '您的卡密',
    'device_id' => '设备唯一标识'
];
$headers = ['X-API-KEY: 您的API密钥'];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);
```

## 📋 功能列表

- [x] 卡密管理
  - [x] SHA1加密存储
  - [x] 批量生成卡密
  - [x] 自定义有效期
  - [x] 设备绑定
  - [x] 停用/启用
  - [x] 导出Excel

- [x] API管理
  - [x] 多密钥支持
  - [x] 调用统计
  - [x] 状态管理
  - [x] 使用记录

- [x] 数据统计
  - [x] 使用趋势
  - [x] 实时统计
  - [x] 图表展示

## 🤝 参与贡献

1. Fork 本仓库
2. 创建新的分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 提交 Pull Request

## 📞 联系方式

- 作者：小小怪
- Email：2456993017@qq.com
- GitHub：[@xiaoxiaoguai-yyds](https://github.com/xiaoxiaoguai-yyds)

## 📄 开源协议

本项目采用 MIT 协议开源，详见 [LICENSE](LICENSE) 文件。

## ⭐ Star 历史

[![Star History Chart](https://api.star-history.com/svg?repos=xiaoxiaoguai-yyds/xxgkami&type=Date)](https://star-history.com/#xiaoxiaoguai-yyds/xxgkami&Date)

## 🙏 鸣谢
感谢所有为这个项目做出贡献的开发者！ 
