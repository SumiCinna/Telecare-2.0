/* TELE-CARE Admin Dashboard
 * Chart rendering and toast dismissal.
 */
document.addEventListener('DOMContentLoaded', () => {
const dashboardData = window.TELECARE_DASHBOARD_DATA || {};
const labels = dashboardData.labels || [];
const dataAppts = dashboardData.appointments || [];
const dataComp = dashboardData.completed || [];
const dataPend = dashboardData.pending || [];
const dataConf = dashboardData.confirmed || [];
const dataCanc = dashboardData.cancelled || [];
const dataRev = dashboardData.revenue || [];
const docNames = dashboardData.doctorNames || [];
const docCounts = dashboardData.doctorCounts || [];
const paidCnt = Number(dashboardData.paidCount || 0);
const unpaidCnt = Number(dashboardData.unpaidCount || 0);

const gridColor = 'rgba(36,68,65,0.07)';
const fontColor = '#9ab0ae';

const baseOpts = {
  responsive:true, maintainAspectRatio:false,
  plugins:{ legend:{ labels:{ font:{family:"'Poppins',sans-serif",size:11}, color:'#244441', boxWidth:10, padding:12 }}},
  scales:{
    x:{ grid:{color:gridColor}, ticks:{color:fontColor,font:{size:10,family:"'Poppins',sans-serif"}} },
    y:{ grid:{color:gridColor}, ticks:{color:fontColor,font:{size:10,family:"'Poppins',sans-serif"},precision:0} }
  }
};

// LINE CHART
new Chart(document.getElementById('lineChart'), {
  type:'line',
  data:{
    labels,
    datasets:[
      { label:'Appointments', data:dataAppts, borderColor:'#3F82E3', backgroundColor:'rgba(63,130,227,0.08)', tension:0.4, fill:true, pointRadius:3, borderWidth:2 },
      { label:'Completed',    data:dataComp,  borderColor:'#0d9488', backgroundColor:'rgba(13,148,136,0.06)', tension:0.4, fill:true, pointRadius:3, borderWidth:2, borderDash:[4,3] }
    ]
  },
  options:{...baseOpts, plugins:{...baseOpts.plugins}}
});

// STACKED BAR
new Chart(document.getElementById('stackedBar'), {
  type:'bar',
  data:{
    labels,
    datasets:[
      { label:'Pending',   data:dataPend, backgroundColor:'#3F82E3', stack:'s' },
      { label:'Completed', data:dataComp, backgroundColor:'#0d9488', stack:'s' },
      { label:'Confirmed', data:dataConf, backgroundColor:'#f59e0b', stack:'s' },
      { label:'Cancelled', data:dataCanc, backgroundColor:'#ef4444', stack:'s' }
    ]
  },
  options:{
    ...baseOpts,
    scales:{
      x:{ stacked:true, grid:{color:gridColor}, ticks:{color:fontColor,font:{size:10,family:"'Poppins',sans-serif"}} },
      y:{ stacked:true, grid:{color:gridColor}, ticks:{color:fontColor,font:{size:10,family:"'Poppins',sans-serif"},precision:0} }
    }
  }
});

// AREA LINE (Revenue)
new Chart(document.getElementById('areaChart'), {
  type:'line',
  data:{
    labels,
    datasets:[{
      label:'Revenue',
      data:dataRev,
      borderColor:'#0d9488',
      backgroundColor:'rgba(13,148,136,0.12)',
      tension:0.4, fill:true, pointRadius:3, borderWidth:2
    }]
  },
  options:{
    ...baseOpts,
    plugins:{...baseOpts.plugins, legend:{display:true, labels:{font:{family:"'Poppins',sans-serif",size:11},color:'#244441',boxWidth:10}}},
    scales:{
      x:{ grid:{color:gridColor}, ticks:{color:fontColor,font:{size:10,family:"'Poppins',sans-serif"}} },
      y:{ grid:{color:gridColor}, ticks:{color:fontColor,font:{size:10,family:"'Poppins',sans-serif"},callback:v=>'₱'+v.toLocaleString()} }
    }
  }
});

// DONUT
new Chart(document.getElementById('donutChart'), {
  type:'doughnut',
  data:{
    labels:['Paid','Unpaid'],
    datasets:[{ data:[paidCnt,unpaidCnt], backgroundColor:['#0d9488','#ef4444'], borderWidth:0, hoverOffset:4 }]
  },
  options:{
    responsive:true, maintainAspectRatio:false, cutout:'65%',
    plugins:{
      legend:{ position:'bottom', labels:{font:{family:"'Poppins',sans-serif",size:11},color:'#244441',boxWidth:10,padding:10} },
      tooltip:{ callbacks:{ label:ctx=>` ${ctx.label}: ${ctx.parsed} (${Math.round(ctx.parsed/(paidCnt+unpaidCnt||1)*100)}%)` } }
    }
  }
});

// DOCTOR BAR
new Chart(document.getElementById('doctorBar'), {
  type:'bar',
  data:{
    labels:docNames.map(n=>'Dr. '+n),
    datasets:[{ label:'Appointments', data:docCounts, backgroundColor:'#7c6ef7', borderRadius:6, borderSkipped:false }]
  },
  options:{
    ...baseOpts,
    plugins:{...baseOpts.plugins, legend:{display:false}},
    scales:{
      x:{ grid:{display:false}, ticks:{color:fontColor,font:{size:9,family:"'Poppins',sans-serif"},maxRotation:30} },
      y:{ grid:{color:gridColor}, ticks:{color:fontColor,font:{size:10,family:"'Poppins',sans-serif"},precision:0} }
    }
  }
});
    const toast = document.querySelector('.toast');
    if (toast) window.setTimeout(() => toast.remove(), 3500);
});
