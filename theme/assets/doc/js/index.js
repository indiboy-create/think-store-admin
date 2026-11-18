(function () {
  'use strict';

  if (!window.layui || typeof window.layui.use !== 'function') {
    return;
  }

  window.layui.use(['form', 'table', 'layer', 'element'], function () {
    var form = window.layui.form;
    var table = window.layui.table;
    var layer = window.layui.layer;
    var main = document.querySelector('.store-admin-main');

    if (!main) {
      return;
    }

    var api = main.getAttribute('data-api') || '';
    var initialUid = parseInt(main.getAttribute('data-uid') || '0', 10) || 0;

    table.render({
      elem: '#store-admin-rules',
      id: 'store-admin-rules',
      data: [],
      page: false,
      size: 'sm',
      cols: [[
        { type: 'numbers', title: '#', width: 50 },
        { field: 'name', title: '规则名称', minWidth: 180 }
      ]],
      text: { none: '暂无可用规则' }
    });

    table.render({
      elem: '#store-admin-roles',
      id: 'store-admin-roles',
      data: [],
      page: false,
      size: 'sm',
      cols: [[
        { field: 'role_id', title: '角色 ID', width: 100 },
        { field: 'title', title: '角色名称', minWidth: 150 },
        { field: 'status', title: '状态', width: 90 }
      ]],
      text: { none: '暂无所属角色' }
    });

    function setCount(id, value) {
      var node = document.getElementById(id);

      if (node) {
        node.textContent = String(value);
      }
    }

    function render(rules, roles) {
      rules = rules || [];
      roles = roles || [];

      table.reload('store-admin-rules', {
        data: rules.map(function (name) {
          return { name: String(name) };
        })
      });

      table.reload('store-admin-roles', {
        data: roles.map(function (item) {
          return {
            role_id: item && item.role_id !== undefined ? item.role_id : '—',
            title: item && item.role && item.role.title ? item.role.title : '—',
            status: item && item.role
              ? (Number(item.role.status) === 1 ? '启用' : '禁用')
              : '—'
          };
        })
      });

      setCount('store-admin-rule-count', rules.length);
      setCount('store-admin-role-count', roles.length);
    }

    function load(uid) {
      if (!api) {
        layer.msg('未配置用户权限查询接口');
        return;
      }

      var loading = layer.load(1, { shade: [0.1, '#ffffff'] });

      fetch(api + '?uid=' + encodeURIComponent(uid), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json()
            .catch(function () {
              return {};
            })
            .then(function (body) {
              return { ok: response.ok, body: body };
            });
        })
        .then(function (result) {
          layer.close(loading);

          if (!result.ok || !result.body || result.body.code !== 0) {
            layer.msg((result.body && result.body.msg) || '查询失败');
            return;
          }

          var data = result.body.data || {};

          render(data.rules, data.roles);
          layer.msg('已加载用户 ' + data.uid + ' 的权限', { icon: 1, time: 1200 });
        })
        .catch(function () {
          layer.close(loading);
          layer.msg('请求失败，请检查路由与登录状态');
        });
    }

    form.on('submit(store-admin-query)', function (data) {
      var uid = parseInt(data.field.uid, 10);

      if (!uid || uid < 1) {
        layer.msg('请输入有效的用户 ID');
        return false;
      }

      load(uid);
      return false;
    });

    if (initialUid > 0) {
      load(initialUid);
    }
  });
})();