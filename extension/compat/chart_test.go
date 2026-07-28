package compat

import "testing"

func TestTranslateChartDataTable(t *testing.T) {
	chart, err := TranslateChart(`{
		"type": "col",
		"title": "Sales",
		"series": [{"name": "Sheet1!$B$1", "categories": "Sheet1!$A$2:$A$4", "values": "Sheet1!$B$2:$B$4"}],
		"dataTable": {"show": true, "showKeys": true}
	}`)
	if err != nil {
		t.Fatal(err)
	}
	if !chart.PlotArea.ShowDataTable {
		t.Error("ShowDataTable should be enabled")
	}
	if !chart.PlotArea.ShowDataTableKeys {
		t.Error("ShowDataTableKeys should follow showKeys")
	}
}

func TestTranslateChartDataTableKeysOff(t *testing.T) {
	chart, err := TranslateChart(`{
		"type": "bar",
		"series": [{"name": "n", "categories": "c", "values": "v"}],
		"dataTable": {"show": true, "showKeys": false}
	}`)
	if err != nil {
		t.Fatal(err)
	}
	if !chart.PlotArea.ShowDataTable {
		t.Error("ShowDataTable should be enabled")
	}
	if chart.PlotArea.ShowDataTableKeys {
		t.Error("ShowDataTableKeys should be false")
	}
}

func TestTranslateChartNoDataTableByDefault(t *testing.T) {
	chart, err := TranslateChart(`{
		"type": "line",
		"series": [{"name": "n", "categories": "c", "values": "v"}]
	}`)
	if err != nil {
		t.Fatal(err)
	}
	if chart.PlotArea.ShowDataTable {
		t.Error("ShowDataTable should default to false")
	}
}
