/*!
 * This file is part of FacturaScripts
 * Copyright (C) 2023-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
function widgetVarianteDraw(t,a){let e="";a.forEach((function(a){let i=a.descripcion;i.length>300&&(i=i.substring(0,300)+"...");let n="";a.precio<0?n=" text-danger":0==a.precio&&(n=" text-warning");let r="";a.stock<0?r=" text-danger":0==a.stock&&(r=" text-warning"),e+='<tr class="clickableRow" onclick="widgetVarianteSelect(\''+t+"', '"+a.match+'\');"><td class="text-center"><a href="'+a.url+'" target="_blank" onclick="event.stopPropagation();"><i class="fa-solid fa-external-link-alt fa-fw"></i></a></td><td><b>'+a.referencia+"</b> "+i+'</td><td class="text-end text-nowrap'+n+'">'+a.precio_str+'</td><td class="text-end text-nowrap'+r+'">'+a.stock_str+"</td></tr>"})),$("#list_"+t).html(e)}function widgetVarianteSearch(t){$("#list_"+t).html("");let a=$("#"+t),e={action:"widget-variante-search",active_tab:a.closest("form").find('input[name="activetab"]').val(),col_name:a.attr("name"),query:$("#modal_"+t+"_q").val(),codfabricante:$("#modal_"+t+"_fab").val(),codfamilia:$("#modal_"+t+"_fam").val(),sort:$("#modal_"+t+"_s").val()};$.ajax({method:"POST",url:window.location.href,data:e,dataType:"json",success:function(a){widgetVarianteDraw(t,a)},error:function(t){alert(t.status+" "+t.responseText)}})}let widgetVarianteSearchTimeouts={};function widgetVarianteSearchKp(t,a){widgetVarianteSearchTimeouts[t]&&clearTimeout(widgetVarianteSearchTimeouts[t]),widgetVarianteSearchTimeouts[t]=setTimeout((function(){widgetVarianteSearch(t)}),400)}function widgetVarianteSelect(t,a){$("#"+t).val(a),$("#modal_"+t).modal("hide"),$("#modal_span_"+t).html(a)}