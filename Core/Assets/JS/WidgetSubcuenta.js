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
function widgetSubaccountDraw(t,a){let c="";a.forEach((function(a){const e=parseFloat(a.saldo||0),o=e.toLocaleString(void 0,{minimumFractionDigits:2,maximumFractionDigits:2}),i=e<0?" text-danger":"";c+='<tr class="clickableRow" onclick="widgetSubaccountSelect(\''+t+"', '"+a.codsubcuenta+'\');"><td class="text-center"><a href="'+a.url+'" target="_blank" onclick="event.stopPropagation();"><i class="fa-solid fa-external-link-alt fa-fw"></i></a></td><td><b>'+a.codsubcuenta+"</b></td><td>"+a.descripcion+'</td><td class="text-end'+i+'">'+o+"</td></tr>"})),$("#list_"+t).html(c)}function widgetSubaccountSearch(t){$("#list_"+t).html("");const a=$("#"+t),c={action:"widget-subcuenta-search",active_tab:a.closest("form").find('input[name="activetab"]').val(),col_name:a.attr("name"),query:$("#modal_"+t+"_q").val(),codejercicio:$("#modal_"+t+"_ej").val(),sort:$("#modal_"+t+"_s").val()};$.ajax({method:"POST",url:window.location.href,data:c,dataType:"json",success:function(a){widgetSubaccountDraw(t,a)},error:function(t){alert(t.status+" "+t.responseText)}})}let widgetSubaccountSearchTimeouts={};function widgetSubaccountSearchKp(t,a){widgetSubaccountSearchTimeouts[t]&&clearTimeout(widgetSubaccountSearchTimeouts[t]),widgetSubaccountSearchTimeouts[t]=setTimeout((function(){widgetSubaccountSearch(t)}),400)}function widgetSubaccountSelect(t,a){$("#"+t).val(a),$("#modal_"+t).modal("hide"),$("#modal_span_"+t).html(a)}