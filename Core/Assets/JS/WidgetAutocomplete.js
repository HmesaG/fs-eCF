/*!
 * This file is part of FacturaScripts
 * Copyright (C) 2017-2020 Carlos Garcia Gomez <carlos@facturascripts.com>
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
function widgetAutocompleteGetData(t,e,a){var i=$("form[id="+t+"]").serializeArray();return $.each(i,(function(t,a){e[a.name]=a.value})),e.action="autocomplete",e.term=a,e}$(document).ready((function(){$(".widget-autocomplete").each((function(){var t={field:$(this).attr("data-field"),fieldcode:$(this).attr("data-fieldcode"),fieldfilter:$(this).attr("data-fieldfilter"),fieldtitle:$(this).attr("data-fieldtitle"),source:$(this).attr("data-source"),strict:$(this).attr("data-strict")},e=$(this).closest("form").attr("id");$(this).autocomplete({source:function(a,i){$.ajax({method:"POST",url:window.location.href,data:widgetAutocompleteGetData(e,t,a.term),dataType:"json",success:function(t){var e=[];t.forEach((function(t){null===t.key||t.key===t.value?e.push(t):e.push({key:t.key,value:t.key+" | "+t.value})})),i(e)},error:function(t){alert(t.status+" "+t.responseText)}})},select:function(a,i){if(null!==i.item.key){$("form[id="+e+"] input[name="+t.field+"]").val(i.item.key);var o=i.item.value.split(" | ");o.length>1?i.item.value=o[1]:i.item.value=o[0]}},open:function(t,e){return $(this).autocomplete("widget").css("z-index",1500),!1}}),$(this).on("keyup",(function(a){"0"===t.strict&&"Enter"!==a.key&&$("form[id="+e+"] input[name="+t.field+"]").val(a.target.value)}))}))}));