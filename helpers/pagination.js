const { SafeString } = require('hbs')

module.exports = (start, total, url, opts) => {
  const { visibles = 7 } = opts.hash

  let begin = start - Math.floor(visibles / 2)
  begin = begin < 1 ? 1 : begin
  let end = begin + visibles - 1
  end = end > total ? total : end
  begin = end - visibles + 1
  begin = begin < 1 ? 1 : begin

  let result = '<div class="sui-pagination pagination-large"><ul>'

  // 上一页
  result += `<li class="prev${start > 1 ? '' : 'disabled'}"><a href="${url.replace('*p', start - 1)}">«上一页</a></li>`

  // 省略号
  if (begin > 1) {
    result += '<li class="dotted"><span>...</span></li>'
  }

  // 数字页码
  for (let i = begin; i <= end; i++) {
    const activeClass = i == start ? ' class="active"' : ''
    result += `<li${activeClass}><a href="${url.replace('*p', i)}">${i}</a></li>`
  }

  // 省略号
  if (end < total) {
    result += '<li class="dotted"><span>...</span></li>'
  }

  // 下一页
  result += `<li class="next${end < total ? '' : 'disabled'}"><a href="${url.replace('*p', start + 1)}">下一页»</a></li>`

  //
  result += `</ul><div><form>共${total}页&nbsp; 到第 <input type="text" class="page-num" name="page"> 页 <button class="page-confirm">确定</button></form></div></div>`

  return new SafeString(result)
}
