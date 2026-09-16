$ErrorActionPreference = 'Stop'
Add-Type -TypeDefinition @'
using System;
using System.Text;
using System.Collections.Generic;
using System.Runtime.InteropServices;
public class WL {
    [DllImport("user32.dll")] public static extern bool EnumWindows(EnumProc cb, IntPtr p);
    [DllImport("user32.dll")] public static extern bool IsWindowVisible(IntPtr h);
    [DllImport("user32.dll")] public static extern bool GetWindowRect(IntPtr h, out RECT r);
    [DllImport("user32.dll")] public static extern int GetWindowThreadProcessId(IntPtr h, out int pid);
    [DllImport("user32.dll", CharSet=CharSet.Unicode)] public static extern int GetClassName(IntPtr h, StringBuilder s, int n);
    [DllImport("user32.dll", CharSet=CharSet.Unicode)] public static extern int GetWindowTextW(IntPtr h, StringBuilder s, int n);
    public delegate bool EnumProc(IntPtr h, IntPtr p);
    [StructLayout(LayoutKind.Sequential)] public struct RECT { public int Left, Top, Right, Bottom; }
    public static List<string> List() {
        List<string> res = new List<string>();
        EnumWindows(delegate(IntPtr h, IntPtr p) {
            if (!IsWindowVisible(h)) return true;
            int pid; GetWindowThreadProcessId(h, out pid);
            RECT r; GetWindowRect(h, out r);
            int w = r.Right - r.Left, ht = r.Bottom - r.Top;
            if (w < 100 || ht < 60) return true;
            StringBuilder c = new StringBuilder(64); GetClassName(h, c, 64);
            StringBuilder t = new StringBuilder(256); GetWindowTextW(h, t, 256);
            res.Add(string.Format("pid={0} class={1} rect={2},{3} {4}x{5} title={6}", pid, c, r.Left, r.Top, w, ht, t));
            return true;
        }, IntPtr.Zero);
        return res;
    }
}
'@
$eps = @(Get-Process editplus -ErrorAction SilentlyContinue | ForEach-Object { $_.Id })
"EditPlus pid: " + ($eps -join ', ')
foreach ($line in [WL]::List()) {
    $pid2 = [int]($line -replace '^pid=(\d+).*$', '$1')
    if ($eps -contains $pid2) { "  [EDITPLUS] $line" }
}
"--- 상위 창 전체 ---"
[WL]::List() | Select-Object -First 20
